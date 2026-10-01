<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\Entitlements;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * Me (API.md §2.3): the user, the own organization, the membership, the derived permissions
 * (§8.1), the current subscription, the entitlements and the unread notification count.
 * AuthTokenPayload is Me plus `token` and `token_type` (pass the plain-text token).
 *
 * @mixin User
 */
final class MeResource extends JsonResource
{
    public function __construct(User $user, private readonly ?string $plainTextToken = null)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $user->loadMissing(['avatarFile', 'membership.organization']);

        $membership = $user->membership;
        $organization = $membership?->organization;
        $entitlements = app(Entitlements::class);

        $data = [
            'user' => (new UserResource($user))->resolve($request),
            'organization' => $organization !== null ? (new OrganizationResource($organization))->resolve($request) : null,
            'membership' => $membership !== null ? [
                'id' => $membership->public_id,
                'role' => $membership->role->value,
                'can_award' => $membership->isOwner() || $membership->can_award,
                'can_purchase' => $membership->isOwner() || $membership->can_purchase,
                'status' => $membership->status->value,
            ] : null,
            'permissions' => array_map(static fn (Permission $permission): string => $permission->value, $user->permissions()),
            'subscription' => $organization !== null ? self::subscription($entitlements, $organization) : null,
            'entitlements' => $organization !== null ? [
                'can_issue' => $entitlements->canIssue($organization),
                'seats_used' => $entitlements->seatsUsed($organization),
                'seats_total' => $entitlements->seatLimit($organization),
            ] : ['can_issue' => false, 'seats_used' => 0, 'seats_total' => 0],
            'unread_notifications_count' => $user->unreadNotifications()->count(),
        ];

        if ($this->plainTextToken !== null) {
            $data['token'] = $this->plainTextToken;
            $data['token_type'] = 'Bearer';
        }

        return $data;
    }

    /**
     * The `subscription` object of Me and Home (API.md §2.3), or null without a current one.
     *
     * CONTRACT-GAP: `days_left` is the whole days until `ends_at`, rounded up (0 once ended);
     * `total_days` the whole days from `starts_at` to `ends_at`, rounded up.
     *
     * @return array<string, mixed>|null
     */
    private static function subscription(Entitlements $entitlements, Organization $organization): ?array
    {
        $subscription = $entitlements->currentSubscription($organization);

        if ($subscription === null) {
            return null;
        }

        $subscription->loadMissing('plan');
        $plan = $subscription->plan;
        $now = CarbonImmutable::instance(Date::now());

        return [
            'plan' => [
                'id' => $plan->public_id,
                'code' => $plan->code,
                'name' => $plan->translated('name'),
            ],
            'source' => $subscription->source->value,
            'status' => $subscription->status->value,
            'ends_at' => Iso::format($subscription->ends_at),
            'days_left' => self::days($now, $subscription->ends_at),
            'total_days' => self::days($subscription->starts_at, $subscription->ends_at),
        ];
    }

    private static function days(?CarbonImmutable $from, ?CarbonImmutable $to): int
    {
        if ($from === null || $to === null || $to->lessThanOrEqualTo($from)) {
            return 0;
        }

        return (int) ceil(($to->getTimestamp() - $from->getTimestamp()) / 86400);
    }
}
