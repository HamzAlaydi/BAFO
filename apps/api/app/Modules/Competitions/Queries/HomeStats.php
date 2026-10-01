<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Queries;

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Enums\CompetitionStatus as S;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLog;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * `GET /home` (API.md §2.13 `Home`): issuer and participant counters, team, subscription,
 * alerts and the activity feed of the caller's organization.
 */
final readonly class HomeStats
{
    /**
     * The audit actions shown in the activity feed (ARCHITECTURE §4.5).
     */
    public const array ACTIVITY_ACTIONS = [
        'competition.created', 'competition.published', 'competition.cancelled', 'competition.closed',
        'award.issued', 'invitation.joined', 'member.added', 'subscription.activated',
    ];

    /**
     * Days before the end of the subscription from which `subscription_expiring` is shown.
     */
    private const int EXPIRING_DAYS = 7;

    public function __construct(private AccessPolicy $access) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Organization $organization): array
    {
        $now = Date::now();
        $since = $now->subDays(30);
        $subscription = $this->access->activeSubscription($organization);

        return [
            'issuer' => $this->issuer($organization, $since),
            'participant' => $this->participant($organization, $since, $now),
            'team' => [
                'members' => Membership::query()->where('organization_id', $organization->id)
                    ->where('status', MembershipStatus::Active->value)->count(),
                'seats_total' => $this->access->seatLimit($organization),
            ],
            'subscription' => $subscription !== null ? $this->subscription($subscription, $now) : null,
            'alerts' => $this->alerts($organization, $subscription, $now),
            'activities' => $this->activities($organization),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function issuer(Organization $organization, CarbonImmutable $since): array
    {
        /** @var Collection<string, int> $byStatus */
        $byStatus = Competition::query()
            ->where('organization_id', $organization->id)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(static fn (mixed $n): int => (int) $n);

        $count = static fn (S ...$statuses): int => array_sum(array_map(
            static fn (S $status): int => $byStatus->get($status->value, 0),
            $statuses,
        ));

        return [
            'active_competitions' => $count(S::Scheduled, S::Live, S::Closed, S::BafoRound),
            'draft_competitions' => $count(S::Draft),
            'live_now' => $count(S::Live),
            'awaiting_award' => $count(S::Closed),
            'offers_received_30d' => Offer::query()
                ->whereIn('competition_id', Competition::query()->select('id')->where('organization_id', $organization->id))
                ->where('accepted_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function participant(Organization $organization, CarbonImmutable $since, CarbonImmutable $now): array
    {
        return [
            'pending_invitations' => Invitation::query()
                ->where('organization_id', $organization->id)
                ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
                ->whereHas('competition', static fn ($q) => $q
                    ->whereIn('status', [S::Scheduled->value, S::Live->value])
                    ->where('invitation_cutoff_at', '>', $now))
                ->count(),
            'active_participations' => Participant::query()
                ->where('organization_id', $organization->id)
                ->whereHas('competition', static fn ($q) => $q->whereIn('status', [
                    S::Scheduled->value, S::Live->value, S::Closed->value, S::BafoRound->value,
                ]))
                ->count(),
            'offers_submitted_30d' => Offer::query()
                ->where('organization_id', $organization->id)
                ->where('accepted_at', '>=', $since)
                ->count(),
            'awards_won' => Award::query()
                ->where('organization_id', $organization->id)
                ->where('status', 'issued')
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscription(Subscription $subscription, CarbonImmutable $now): array
    {
        $subscription->loadMissing('plan');

        return [
            'plan' => [
                'id' => $subscription->plan->public_id,
                'code' => $subscription->plan->code,
                'name' => $subscription->plan->translated('name'),
            ],
            'status' => $subscription->status->value,
            'days_left' => self::daysLeft($subscription, $now),
            'total_days' => $subscription->starts_at !== null && $subscription->ends_at !== null
                ? (int) round($subscription->starts_at->diffInDays($subscription->ends_at)) : null,
            'ends_at' => Iso::format($subscription->ends_at),
        ];
    }

    /**
     * CONTRACT-GAP: API.md §2.13 names the alert codes but not their conditions. Chosen:
     * `subscription_expiring` within 7 days of the end; `subscription_expired` / `plan_required`
     * without a current subscription (expired when the latest one expired); `trial_available`
     * when the trial was never used and nothing was paid; `billing_profile_incomplete` from the
     * organization profile.
     *
     * `params` is always a JSON object: `{}` when the alert has no parameters, never `[]`.
     *
     * @return list<array{code: string, params: object}>
     */
    private function alerts(Organization $organization, ?Subscription $current, CarbonImmutable $now): array
    {
        $alerts = [];

        if ($current !== null) {
            $daysLeft = self::daysLeft($current, $now);

            if ($daysLeft !== null && $daysLeft <= self::EXPIRING_DAYS) {
                $alerts[] = ['code' => 'subscription_expiring', 'params' => ['days_left' => $daysLeft]];
            }
        } else {
            $latest = Subscription::query()->where('organization_id', $organization->id)
                ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Expired->value])
                ->latest('id')->first();

            $alerts[] = $latest?->status === SubscriptionStatus::Expired
                ? ['code' => 'subscription_expired', 'params' => []]
                : ['code' => 'plan_required', 'params' => []];

            $paid = Subscription::query()->where('organization_id', $organization->id)
                ->where('source', SubscriptionSource::Paid->value)
                ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Expired->value, SubscriptionStatus::Superseded->value])
                ->exists();

            if ($organization->trial_used_at === null && ! $paid) {
                $alerts[] = ['code' => 'trial_available', 'params' => []];
            }
        }

        if (! $organization->isBillingProfileComplete()) {
            $alerts[] = ['code' => 'billing_profile_incomplete', 'params' => []];
        }

        return array_map(
            static fn (array $alert): array => ['code' => $alert['code'], 'params' => (object) $alert['params']],
            $alerts,
        );
    }

    /**
     * The last 10 feed entries (ARCHITECTURE §4.5), newest first.
     *
     * CONTRACT-GAP: `audit_logs` has no public id; `id` is an opaque, stable HMAC of the row id
     * (26 lowercase hex characters, not a ULID). API.md §2.13 documents it as opaque.
     *
     * @return list<array<string, mixed>>
     */
    private function activities(Organization $organization): array
    {
        $logs = AuditLog::query()
            ->forOrganization($organization->id, self::ACTIVITY_ACTIONS)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $competitionIds = $logs->map(static function (AuditLog $log): ?string {
            $id = $log->subject_type === 'competition' ? $log->subject_public_id : ($log->meta['competition_id'] ?? null);

            return is_string($id) ? $id : null;
        })->filter()->unique()->values()->all();

        /** @var array<string, string> $titles */
        $titles = Competition::withTrashed()->whereIn('public_id', $competitionIds)->pluck('title', 'public_id')->all();

        /** @var array<string, string> $awardCompetitions */
        $awardCompetitions = Award::query()
            ->join('competitions', 'competitions.id', '=', 'awards.competition_id')
            ->whereIn('awards.public_id', $logs->where('subject_type', 'award')->pluck('subject_public_id')->filter()->all())
            ->pluck('competitions.title', 'awards.public_id')
            ->all();

        $key = (string) config('app.key');

        return $logs->map(static function (AuditLog $log) use ($titles, $awardCompetitions, $key): array {
            $competitionId = $log->subject_type === 'competition' ? $log->subject_public_id : ($log->meta['competition_id'] ?? null);
            $competitionId = is_string($competitionId) ? $competitionId : null;

            $subject = match (true) {
                $competitionId !== null && $log->subject_type !== 'award' => [
                    'type' => 'competition', 'id' => $competitionId, 'title' => $titles[$competitionId] ?? null,
                ],
                default => [
                    'type' => $log->subject_type,
                    'id' => $log->subject_public_id,
                    'title' => $log->subject_type === 'award' ? ($awardCompetitions[(string) $log->subject_public_id] ?? null) : null,
                ],
            };

            return [
                'id' => substr(hash_hmac('sha256', 'audit_log:'.$log->id, $key), 0, 26),
                'action' => $log->action,
                'occurred_at' => Iso::format($log->occurred_at),
                'actor' => ['name' => $log->actor_label],
                'subject' => $subject,
            ];
        })->values()->all();
    }

    private static function daysLeft(Subscription $subscription, CarbonImmutable $now): ?int
    {
        if ($subscription->ends_at === null) {
            return null;
        }

        return max(0, (int) ceil($now->diffInSeconds($subscription->ends_at, false) / 86_400));
    }
}
