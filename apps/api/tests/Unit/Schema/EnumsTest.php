<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Enums\ApiScope;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\WebhookEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Every string-backed enum of the eight module Enums directories, by module snake name.
 *
 * @return array<string, array{0: string, 1: class-string<BackedEnum>}>
 */
function schemaModuleEnums(): array
{
    $enums = [];

    foreach (['Catalog', 'Identity', 'Integrations', 'Competitions', 'Bidding', 'Billing', 'Notifications', 'Admin'] as $module) {
        // Datasets are resolved before the application boots: no app_path() here.
        foreach (glob(dirname(__DIR__, 3)."/app/Modules/{$module}/Enums/*.php") ?: [] as $file) {
            /** @var class-string<BackedEnum> $class */
            $class = "App\\Modules\\{$module}\\Enums\\".basename($file, '.php');
            $enums[class_basename($class)] = [Str::snake($module), $class];
        }
    }

    return $enums;
}

it('labels every enum value in Arabic and English', function (string $module, string $enum) {
    expect(enum_exists($enum))->toBeTrue()
        ->and((new ReflectionEnum($enum))->isBacked())->toBeTrue();

    foreach ($enum::cases() as $case) {
        $key = "{$module}.enums.".Str::snake(class_basename($enum)).".{$case->value}";

        expect(Lang::has($key, 'ar', false))->toBeTrue("missing ar {$key}")
            ->and(Lang::has($key, 'en', false))->toBeTrue("missing en {$key}")
            ->and(method_exists($case, 'label'))->toBeTrue()
            ->and($case->label('ar'))->toBe(__($key, [], 'ar'))
            ->and($case->label('en'))->toBe(__($key, [], 'en'));
    }
})->with(fn (): array => schemaModuleEnums());

it('keeps the Arabic and English module lang files in sync', function (string $module) {
    $ar = Arr::dot(require lang_path("ar/{$module}.php"));
    $en = Arr::dot(require lang_path("en/{$module}.php"));

    expect(array_keys($ar))->toEqualCanonicalizing(array_keys($en));
})->with(['catalog', 'identity', 'integrations', 'competitions', 'bidding', 'billing', 'notifications', 'admin']);

it('uses the §7.1 direction sign', function () {
    expect(Direction::Tender->sign())->toBe(-1)
        ->and(Direction::Auction->sign())->toBe(1);
});

it('derives organization permissions from the §8.1 matrix', function (OrgRole $role, bool $canAward, bool $canPurchase, array $expected) {
    $membership = new Membership([
        'role' => $role,
        'can_award' => $canAward,
        'can_purchase' => $canPurchase,
        'status' => MembershipStatus::Active,
    ]);

    expect(array_map(fn (Permission $permission): string => $permission->value, OrgRole::permissions($membership)))
        ->toBe($expected);
})->with([
    'owner (flags ignored)' => [OrgRole::Owner, false, false, array_map(fn (Permission $p): string => $p->value, Permission::cases())],
    'admin without flags' => [OrgRole::Admin, false, false, [
        'organization.update', 'team.manage', 'billing.view', 'competitions.create', 'competitions.manage_all',
        'participation.submit_offers', 'integrations.manage',
    ]],
    'admin with flags' => [OrgRole::Admin, true, true, [
        'organization.update', 'team.manage', 'billing.view', 'billing.purchase', 'competitions.create',
        'competitions.manage_all', 'competitions.award', 'participation.submit_offers', 'integrations.manage',
    ]],
    'member without flags' => [OrgRole::Member, false, false, ['competitions.create', 'participation.submit_offers']],
    'member with flags' => [OrgRole::Member, true, true, [
        'billing.purchase', 'competitions.create', 'competitions.award', 'participation.submit_offers',
    ]],
]);

it('pre-selects the §14.4 default scopes', function () {
    $defaults = array_map(fn (ApiScope $scope): string => $scope->value, ApiScope::defaults());

    foreach (['competitions:publish', 'competitions:manage', 'webhooks:manage'] as $optIn) {
        expect($defaults)->not->toContain($optIn);
    }

    expect($defaults)->toContain('organization:read', 'lookups:read', 'vendors:write', 'competitions:write', 'invitations:write', 'awards:sync')
        ->and(ApiScope::descriptions())->toHaveCount(14)
        ->and(ApiScope::descriptions()['offers:read'])->toBe('Read offers');
});

it('answers the small model predicates', function () {
    $now = CarbonImmutable::now();

    $organization = new Organization([
        'legal_name_ar' => 'شركة المصدر للتجارة', 'cr_number' => '1010123456', 'city' => 'الرياض',
        'address_building_number' => '1234', 'address_street' => 'طريق الملك فهد', 'address_district' => 'حي العليا',
        'address_postal_code' => '12345', 'vat_registered' => true, 'vat_number' => null,
    ]);
    $subscription = new Subscription(['status' => SubscriptionStatus::Active, 'starts_at' => $now->subDay(), 'ends_at' => $now->addDay()]);
    $key = new ApiKey(['expires_at' => $now->addDay()]);
    $endpoint = new WebhookEndpoint(['event_types' => ['award.issued']]);

    expect($organization->missingBillingProfileFields())->toBe(['vat_number'])
        ->and($subscription->isCurrentAt($now))->toBeTrue()
        ->and($subscription->isCurrentAt($now->addDays(2)))->toBeFalse()
        ->and($key->isUsableAt($now))->toBeTrue()
        ->and($key->isUsableAt($now->addDays(2)))->toBeFalse()
        ->and($endpoint->listensTo('award.issued'))->toBeTrue()
        ->and($endpoint->listensTo('offer.submitted'))->toBeFalse();
});
