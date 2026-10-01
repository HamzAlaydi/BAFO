<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\EInvoiceStatus;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionExpired;
use App\Modules\Billing\Events\SubscriptionExpiring;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Billing\Services\Gateways\FakePaymentGateway;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

it('schedules the §12 billing commands', function () {
    $events = collect(app(Schedule::class)->events())->mapWithKeys(fn ($event) => [trim(str($event->command)->after('artisan')->replace(["'", '"'], '')->toString()) => $event->expression]);

    expect($events->get('billing:expire-subscriptions'))->toBe('*/5 * * * *')
        ->and($events->get('billing:subscription-reminders'))->toBe('0 6 * * *')
        ->and($events->get('billing:reconcile-payments'))->toBe('*/5 * * * *')
        ->and($events->get('billing:retry-einvoices'))->toBe('*/5 * * * *');
});

describe('billing:expire-subscriptions', function () {
    it('expires ended subscriptions and announces the lapse', function () {
        Event::fake([SubscriptionExpired::class]);
        $organization = Organization::factory()->create();
        $ended = Billing::subscribe($organization, 'pro', startsAt: CarbonImmutable::now()->subMonth(), endsAt: CarbonImmutable::now()->subMinute());
        $running = Billing::subscribe(Organization::factory()->create(), 'pro');

        $this->artisan('billing:expire-subscriptions')->assertSuccessful();

        expect($ended->refresh()->status)->toBe(SubscriptionStatus::Expired)
            ->and($ended->expired_at)->not->toBeNull()
            ->and($running->refresh()->status)->toBe(SubscriptionStatus::Active);
        Event::assertDispatchedTimes(SubscriptionExpired::class, 1);
    });

    it('does not announce an expiry when a queued renewal takes over', function () {
        Event::fake([SubscriptionExpired::class]);
        $organization = Organization::factory()->create();
        $ended = Billing::subscribe($organization, 'pro', startsAt: CarbonImmutable::now()->subMonth(), endsAt: CarbonImmutable::now()->subMinute());
        Billing::subscribe($organization, 'pro', startsAt: CarbonImmutable::now()->subMinute(), endsAt: CarbonImmutable::now()->addMonth());

        $this->artisan('billing:expire-subscriptions')->assertSuccessful();

        expect($ended->refresh()->status)->toBe(SubscriptionStatus::Expired);
        Event::assertNotDispatched(SubscriptionExpired::class);
    });
});

describe('billing:subscription-reminders', function () {
    it('sends T−7, T−3 and T−1 once each', function () {
        Event::fake([SubscriptionExpiring::class]);
        $start = CarbonImmutable::parse('2026-10-01T06:00:00Z');
        $this->travelTo($start);
        $subscription = Billing::subscribe(Organization::factory()->create(), 'pro', startsAt: $start->subDays(20), endsAt: $start->addDays(10));

        $days = [];

        foreach (range(0, 10) as $day) {
            $this->travelTo($start->addDays($day));
            $this->artisan('billing:subscription-reminders')->assertSuccessful();
        }

        Event::assertDispatched(SubscriptionExpiring::class, function (SubscriptionExpiring $event) use (&$days): bool {
            $days[] = $event->daysLeft;

            return true;
        });

        expect($days)->toBe([7, 3, 1])
            ->and($subscription->refresh()->reminders_sent)->toBe([7, 3, 1]);
    });

    it('catches up a missed threshold without repeating the larger ones', function () {
        Event::fake([SubscriptionExpiring::class]);
        $subscription = Billing::subscribe(Organization::factory()->create(), 'pro', startsAt: CarbonImmutable::now()->subDays(28), endsAt: CarbonImmutable::now()->addDays(2));

        $this->artisan('billing:subscription-reminders')->assertSuccessful();
        $this->artisan('billing:subscription-reminders')->assertSuccessful();

        Event::assertDispatchedTimes(SubscriptionExpiring::class, 1);
        Event::assertDispatched(SubscriptionExpiring::class, fn (SubscriptionExpiring $e) => $e->daysLeft === 2);
        expect($subscription->refresh()->reminders_sent)->toEqualCanonicalizing([7, 3]);
    });
});

describe('billing:reconcile-payments', function () {
    it('applies the gateway state, expires stale checkouts and voids expired holds', function () {
        Storage::fake('private');
        $paid = Payment::factory()->create(['created_at' => now()->subMinutes(10), 'metadata' => [FakePaymentGateway::STATE_KEY => 'paid']]);
        $stale = Payment::factory()->create(['created_at' => now()->subHour(), 'expires_at' => now()->subMinute()]);
        $fresh = Payment::factory()->create(['created_at' => now()->subMinute()]);
        $hold = SponsoredPass::factory()->pending()->create(['hold_expires_at' => now()->subMinute()]);

        $this->artisan('billing:reconcile-payments')->assertSuccessful();

        expect($paid->refresh()->status)->toBe(PaymentStatus::Succeeded)
            ->and($stale->refresh()->status)->toBe(PaymentStatus::Expired)
            ->and($stale->failure_code)->toBe('expired')
            ->and($fresh->refresh()->status)->toBe(PaymentStatus::Pending)
            ->and($hold->refresh()->status)->toBe(PassStatus::Void);
    });

    it('confirms a late payment after expiry and funds the sponsorship', function () {
        Storage::fake('private');
        Billing::fakeCompetitionActions();
        [$owner, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition);
        Billing::actingAs($owner);
        Invitation::factory()->create(['competition_id' => $competition->id]);

        $id = $this->postJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/checkout", [
            'intent' => 'publish', 'return_url' => Billing::RETURN_URL,
        ])->assertCreated()->json('data.id');
        $payment = Payment::query()->where('public_id', $id)->firstOrFail();

        $this->travel(31)->minutes();
        $this->artisan('billing:reconcile-payments')->assertSuccessful();
        expect($payment->refresh()->status)->toBe(PaymentStatus::Expired);

        // The gateway confirms late: verify applies it and the slots are funded.
        $payment->forceFill(['metadata' => [...$payment->metadata, FakePaymentGateway::STATE_KEY => 'paid']])->save();
        $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')->assertOk()->assertJsonPath('data.status', 'succeeded');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded)
            ->and($sponsorship->refresh()->funded_passes)->toBe(1);
    });
});

describe('billing:retry-einvoices', function () {
    it('issues pending and failed e-invoices below 10 attempts', function () {
        Storage::fake('private');
        $pending = Invoice::factory()->create(['einvoice_status' => EInvoiceStatus::Pending, 'einvoice_attempts' => 0, 'pdf_file_id' => null]);
        $failed = Invoice::factory()->create(['einvoice_status' => EInvoiceStatus::Failed, 'einvoice_attempts' => 3, 'pdf_file_id' => null]);
        $givenUp = Invoice::factory()->create(['einvoice_status' => EInvoiceStatus::Failed, 'einvoice_attempts' => 10, 'pdf_file_id' => null]);

        $this->artisan('billing:retry-einvoices')->assertSuccessful();

        expect($pending->refresh()->einvoice_status)->toBe(EInvoiceStatus::Cleared)
            ->and($pending->pdf_file_id)->not->toBeNull()
            ->and($pending->qr_payload)->not->toBeNull()
            ->and($failed->refresh()->einvoice_status)->toBe(EInvoiceStatus::Cleared)
            ->and($failed->einvoice_attempts)->toBe(4)
            ->and($givenUp->refresh()->einvoice_status)->toBe(EInvoiceStatus::Failed);
    });
});
