<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Events\SubscriptionActivated;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionLookup;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin "Grant subscription" (ARCHITECTURE §13.3, §16): a `source = grant` subscription with
 * a plan, seats, a period and a reason. It supersedes the current subscription when the grant
 * is current now. Called from Filament with `Actor::forAdmin()`.
 */
final readonly class GrantSubscription
{
    public function __construct(private SubscriptionLookup $subscriptions) {}

    public function handle(
        Organization $organization,
        Plan $plan,
        int $seats,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $reason,
        Actor $actor,
    ): Subscription {
        if ($seats < 1 || ! $endsAt->greaterThan($startsAt) || trim($reason) === '') {
            throw ValidationException::withMessages(array_filter([
                'seats' => $seats < 1 ? [$this->message('billing.validation.seats_positive')] : null,
                'ends_at' => ! $endsAt->greaterThan($startsAt) ? [$this->message('billing.validation.period_invalid')] : null,
                'reason' => trim($reason) === '' ? [$this->message('billing.validation.reason_required')] : null,
            ]));
        }

        if (! $plan->is_active) {
            throw new ApiException('plan_not_available', 'billing.errors.plan_not_available', 422);
        }

        return DB::transaction(function () use ($organization, $plan, $seats, $startsAt, $endsAt, $reason, $actor): Subscription {
            $now = CarbonImmutable::now();
            $current = $startsAt->lessThanOrEqualTo($now) ? $this->subscriptions->current($organization->id, $now) : null;

            if ($current !== null) {
                $current->forceFill(['status' => SubscriptionStatus::Superseded, 'superseded_at' => $now])->save();
            }

            $subscription = Subscription::query()->create([
                'organization_id' => $organization->id,
                'plan_id' => $plan->id,
                'source' => SubscriptionSource::Grant,
                'interval' => null,
                'seats' => $seats,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'replaces_subscription_id' => $current?->id,
                'granted_by_admin_id' => $actor->adminId,
                'grant_reason' => trim($reason),
                'activated_at' => $now,
            ]);

            AuditLogger::log('subscription.activated', $subscription, meta: [
                'source' => SubscriptionSource::Grant->value,
                'plan_code' => $plan->code,
                'reason' => trim($reason),
                'superseded' => $current?->public_id,
            ], actor: $actor, organizationId: $organization->id);

            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }

    private function message(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
