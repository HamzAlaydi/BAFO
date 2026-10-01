<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLog;
use App\Support\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

describe('GET /billing/subscription', function () {
    it('returns the overview with the current, upcoming and past subscriptions', function () {
        $owner = Billing::actingAs(Billing::member());
        $organization = $owner->membership->organization;
        Billing::member($organization, OrgRole::Member);

        $past = Billing::subscribe($organization, 'plus', startsAt: CarbonImmutable::now()->subMonths(2), endsAt: CarbonImmutable::now()->subMonth());
        $past->forceFill(['status' => SubscriptionStatus::Expired])->save();
        $current = Billing::subscribe($organization, 'pro', startsAt: CarbonImmutable::now()->subDays(9), endsAt: CarbonImmutable::now()->addDays(21));
        $upcoming = Billing::subscribe($organization, 'pro', startsAt: CarbonImmutable::now()->addDays(21), endsAt: CarbonImmutable::now()->addDays(51));

        $response = $this->getJson('/api/app/v1/billing/subscription')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'current' => ['id', 'plan' => ['id', 'code', 'name'], 'source', 'interval', 'seats', 'status', 'starts_at', 'ends_at',
                    'days_left', 'total_days', 'amounts' => ['subtotal_minor', 'credit_minor', 'discount_minor', 'vat_minor', 'total_minor'], 'created_at'],
                'upcoming', 'history', 'trial_available', 'seats_used', 'seats_total',
            ]]);

        expect($response->json('data.current.id'))->toBe($current->public_id)
            ->and($response->json('data.current.days_left'))->toBe(21)
            ->and($response->json('data.current.total_days'))->toBe(30)
            ->and($response->json('data.current.amounts.vat_minor'))->toBe(22_500)
            ->and($response->json('data.upcoming.id'))->toBe($upcoming->public_id)
            ->and($response->json('data.history.*.id'))->toBe([$past->public_id])
            ->and($response->json('data.trial_available'))->toBeFalse()
            ->and($response->json('data.seats_used'))->toBe(2)
            ->and($response->json('data.seats_total'))->toBe(3);
    });

    it('offers the trial to a new organization and counts one seat', function () {
        Billing::actingAs(Billing::member());

        $this->getJson('/api/app/v1/billing/subscription')
            ->assertOk()
            ->assertJsonPath('data.current', null)
            ->assertJsonPath('data.trial_available', true)
            ->assertJsonPath('data.seats_total', 1);
    });

    it('requires billing.view', function () {
        Billing::actingAs(Billing::member(role: OrgRole::Member));

        $this->getJson('/api/app/v1/billing/subscription')->assertForbidden()->assertJsonPath('code', 'forbidden');
    });

    it('requires authentication', function () {
        $this->getJson('/api/app/v1/billing/subscription')->assertUnauthorized();
    });
});

describe('POST /billing/trial', function () {
    it('starts the 30-day trial on the plus plan once', function () {
        Event::fake([SubscriptionActivated::class]);
        $owner = Billing::actingAs(Billing::member());

        $response = $this->postJson('/api/app/v1/billing/trial')
            ->assertCreated()
            ->assertJsonPath('data.source', 'trial')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.code', 'plus')
            ->assertJsonPath('data.interval', null)
            ->assertJsonPath('data.amounts', null)
            ->assertJsonPath('data.seats', 2)
            ->assertJsonPath('data.total_days', 30);

        $organization = $owner->membership->organization->refresh();
        expect($organization->trial_used_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'subscription.activated')->where('organization_id', $organization->id)->exists())->toBeTrue();
        Event::assertDispatched(SubscriptionActivated::class, fn (SubscriptionActivated $e) => $e->subscription->public_id === $response->json('data.id'));

        $this->postJson('/api/app/v1/billing/trial')->assertStatus(409)->assertJsonPath('code', 'trial_not_available');
    });

    it('uses the trial settings', function () {
        app(Settings::class)->set('billing.trial_days', 14, null);
        app(Settings::class)->set('billing.trial_plan_code', 'pro', null);
        Billing::actingAs(Billing::member());

        $this->postJson('/api/app/v1/billing/trial')
            ->assertCreated()
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.total_days', 14);
    });

    it('refuses the trial after a paid subscription', function () {
        $owner = Billing::actingAs(Billing::member());
        $paid = Billing::subscribe($owner->membership->organization, startsAt: CarbonImmutable::now()->subMonths(3), endsAt: CarbonImmutable::now()->subMonths(2));
        $paid->forceFill(['status' => SubscriptionStatus::Expired])->save();

        $this->postJson('/api/app/v1/billing/trial')->assertStatus(409)->assertJsonPath('code', 'trial_not_available');
    });

    it('refuses the trial while a grant is current', function () {
        $owner = Billing::actingAs(Billing::member());
        Billing::subscribe($owner->membership->organization, source: SubscriptionSource::Grant);

        $this->postJson('/api/app/v1/billing/trial')->assertStatus(409)->assertJsonPath('code', 'trial_not_available');
        expect(Subscription::query()->where('source', SubscriptionSource::Trial->value)->count())->toBe(0);
    });

    it('requires billing.purchase', function () {
        Billing::actingAs(Billing::member(role: OrgRole::Member));

        $this->postJson('/api/app/v1/billing/trial')->assertForbidden();
    });

    it('requires authentication', function () {
        $this->postJson('/api/app/v1/billing/trial')->assertUnauthorized();
    });

    it('lets an admin with can_purchase start the trial', function () {
        $organization = Organization::factory()->create();
        Billing::actingAs(Billing::member($organization, OrgRole::Admin));

        $this->postJson('/api/app/v1/billing/trial')->assertCreated();
    });
});
