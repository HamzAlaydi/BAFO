<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use App\Modules\Billing\Database\Seeders\BillingReferenceSeeder;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\CompetitionActions;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

/**
 * Billing test data: members with the §8.1 roles, the reference plans, subscriptions, and
 * doubles for the Competitions actions Billing calls after a sponsorship payment.
 */
final class Billing
{
    public const string RETURN_URL = 'http://localhost:3000/ar/dashboard/billing/checkout/return';

    /**
     * An active, verified member (the owner by default) of a new or given organization.
     */
    public static function member(?Organization $organization = null, OrgRole $role = OrgRole::Owner, array $membership = []): User
    {
        $organization ??= Organization::factory()->create();

        $user = User::factory()->withMembership($organization, $role)->create();

        if ($membership !== []) {
            $user->membership?->forceFill($membership)->save();
            $user->unsetRelation('membership');
        }

        return $user;
    }

    /**
     * Signs the user in (Sanctum) and sets the current actor, as ResolveActor does.
     */
    public static function actingAs(User $user): User
    {
        Auth::forgetGuards();
        Sanctum::actingAs($user);
        CurrentActor::set(Actor::forUser($user));

        return $user;
    }

    public static function plans(): void
    {
        (new BillingReferenceSeeder)->run();
    }

    public static function plan(string $code = 'pro'): Plan
    {
        self::plans();

        return Plan::query()->where('code', $code)->firstOrFail();
    }

    /**
     * A current subscription of the organization.
     */
    public static function subscribe(
        Organization $organization,
        string $planCode = 'pro',
        SubscriptionSource $source = SubscriptionSource::Paid,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        BillingInterval $interval = BillingInterval::Monthly,
    ): Subscription {
        $plan = self::plan($planCode);
        $startsAt ??= CarbonImmutable::now()->subDay();
        $endsAt ??= $interval->periodEnd($startsAt);
        $paid = $source === SubscriptionSource::Paid;
        $unit = $paid ? ($interval === BillingInterval::Annual ? $plan->annual_price_minor : $plan->monthly_price_minor) : null;

        return Subscription::query()->create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'source' => $source,
            'interval' => $paid ? $interval : null,
            'seats' => $plan->seats ?? 4,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'unit_price_minor' => $unit,
            'subtotal_minor' => $unit,
            'discount_minor' => $paid ? 0 : null,
            'credit_minor' => $paid ? 0 : null,
            'vat_minor' => $unit === null ? null : Money::vat($unit),
            'total_minor' => $unit === null ? null : $unit + Money::vat($unit),
            'activated_at' => $startsAt,
        ]);
    }

    /**
     * An issuer organization (sponsorship enabled) with a pro plan, its owner, and a draft
     * competition the owner created.
     *
     * @return array{0: User, 1: Competition}
     */
    public static function sponsoringIssuer(array $competition = []): array
    {
        $organization = Organization::factory()->sponsorshipEnabled()->create();
        $owner = self::member($organization);
        self::subscribe($organization);

        $model = Competition::factory()->create([
            'organization_id' => $organization->id,
            'created_by_user_id' => $owner->id,
            ...$competition,
        ]);

        return [$owner, $model];
    }

    public static function sponsorship(Competition $competition, array $attributes = []): CompetitionSponsorship
    {
        return CompetitionSponsorship::factory()->create([
            'competition_id' => $competition->id,
            'organization_id' => $competition->organization_id,
            ...$attributes,
        ]);
    }

    /**
     * Doubles for the Competitions actions (CompetitionActions resolves them by name) that
     * record their calls; `$fail` makes them throw instead.
     */
    public static function fakeCompetitionActions(?\Throwable $fail = null): CompetitionActionsSpy
    {
        $spy = new CompetitionActionsSpy($fail);

        app()->instance(CompetitionActions::PUBLISH, $spy->publisher());
        app()->instance(CompetitionActions::INVITE, $spy->inviter());

        return $spy;
    }
}
