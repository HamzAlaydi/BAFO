<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\BillingSettings;
use App\Modules\Billing\Services\SubscriptionLookup;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `POST /billing/trial` (ARCHITECTURE §13.3): a `source = trial` subscription on the plan of
 * setting `billing.trial_plan_code`, active now for `billing.trial_days`, once per
 * organization and never after a paid subscription. Records `organizations.trial_used_at`.
 */
final readonly class StartTrial
{
    public function __construct(
        private BillingSettings $settings,
        private SubscriptionLookup $subscriptions,
    ) {}

    public function handle(Organization $organization, Actor $actor): Subscription
    {
        return DB::transaction(function () use ($organization, $actor): Subscription {
            /** @var Organization $locked */
            $locked = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            $now = CarbonImmutable::now();

            // CONTRACT-GAP: §13.3 lists the trial rules; a trial on top of a current subscription
            // (e.g. an admin grant) would overlap it, so that is refused too.
            if ($locked->trial_used_at !== null
                || $this->subscriptions->hasPaidHistory($locked->id)
                || $this->subscriptions->current($locked->id, $now) !== null) {
                throw new ApiException('trial_not_available', 'billing.errors.trial_not_available', 409);
            }

            $plan = Plan::query()->where('code', $this->settings->trialPlanCode())->where('is_active', true)->first()
                ?? throw new ApiException('plan_not_available', 'billing.errors.plan_not_available', 422);

            $subscription = Subscription::query()->create([
                'organization_id' => $locked->id,
                'plan_id' => $plan->id,
                'source' => SubscriptionSource::Trial,
                'interval' => null,
                'seats' => $plan->seats ?? 1,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $now,
                'ends_at' => $now->addDays($this->settings->trialDays()),
                'activated_at' => $now,
            ]);

            // CONTRACT-GAP: `trial_used_at` lives on the Identity table; §13.3 assigns this write
            // to the trial flow and §3.6 has no Identity contract for it.
            $locked->forceFill(['trial_used_at' => $now])->save();

            AuditLogger::log('subscription.activated', $subscription, meta: [
                'source' => SubscriptionSource::Trial->value,
                'plan_code' => $plan->code,
            ], actor: $actor, organizationId: $locked->id);

            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }
}
