<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Actions\StartTrial;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Http\Resources\SubscriptionResource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionLookup;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /billing/subscription` (perm `billing.view`) and `POST /billing/trial` (perm
 * `billing.purchase`), API.md §1.7.
 */
final class SubscriptionController extends BillingController
{
    /**
     * `SubscriptionOverview` (API.md §2.10): current, upcoming, the history (superseded and
     * expired), whether the trial is available, and seat usage.
     */
    public function show(Request $request, SubscriptionLookup $subscriptions, AccessPolicy $access): JsonResponse
    {
        $this->authorize('viewAny', Subscription::class);
        $organization = $this->organization($this->user($request));

        $current = $subscriptions->current($organization->id);
        $upcoming = $subscriptions->upcoming($organization->id);

        // CONTRACT-GAP: API.md does not define the history; it lists the ended subscriptions
        // (superseded and expired), newest first, at most 50.
        $history = Subscription::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [SubscriptionStatus::Superseded->value, SubscriptionStatus::Expired->value])
            ->with('plan')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $trialAvailable = $organization->trial_used_at === null
            && $current === null
            && ! $subscriptions->hasPaidHistory($organization->id);

        return $this->ok([
            'current' => $current === null ? null : SubscriptionResource::make($current)->resolve($request),
            'upcoming' => $upcoming === null ? null : SubscriptionResource::make($upcoming)->resolve($request),
            'history' => SubscriptionResource::collection($history)->resolve($request),
            'trial_available' => $trialAvailable,
            'seats_used' => $subscriptions->seatsUsed($organization->id),
            'seats_total' => $access->seatLimit($organization),
        ]);
    }

    public function trial(Request $request, StartTrial $startTrial): JsonResponse
    {
        $this->authorize('create', Subscription::class);
        $organization = $this->organization($this->user($request));

        return $this->created(SubscriptionResource::make($startTrial->handle($organization, CurrentActor::get())));
    }
}
