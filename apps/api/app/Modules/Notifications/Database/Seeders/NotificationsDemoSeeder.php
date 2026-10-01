<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Database\Seeders;

use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Models\Award;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Services\CompetitionAudience;
use App\Modules\Notifications\Support\NotificationRoutes;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;

/**
 * A few in-app notifications per demo user (docs/build/DEMO.md), built from the demo data the
 * other modules seeded, so every link opens a real page. Database channel only: no push and no
 * mail. The newest notification of each user stays unread; the older ones are read.
 */
final class NotificationsDemoSeeder extends Seeder
{
    private const int PER_USER = 4;

    public function run(): void
    {
        foreach (DemoSeeder::ORGANIZATIONS as $organizationKey => $organization) {
            foreach (array_keys($organization['users']) as $userKey) {
                $user = User::query()->where('email', DemoSeeder::user($organizationKey.'.'.$userKey)['email'])->first();

                if ($user?->membership !== null) {
                    $this->seedFor($user, $user->membership->organization_id);
                }
            }
        }
    }

    private function seedFor(User $user, int $organizationId): void
    {
        $items = array_slice(array_filter([
            ...$this->asIssuer($organizationId),
            ...$this->asParticipant($organizationId),
            ...($user->hasPermission(Permission::BillingView) ? $this->billing($organizationId) : []),
        ]), 0, self::PER_USER);

        $now = CarbonImmutable::now();

        foreach ($items as $index => [$type, $payload]) {
            // Seeders that run Actions may already have notified the user through the listeners.
            if ($this->alreadyNotified($user, $type, $payload)) {
                continue;
            }

            $class = $type->notificationClass();
            $notification = $class::fromPayload($payload);
            $user->notifyNow($notification, ['database']);

            $createdAt = $now->subHours(3 * $index + 1);
            DatabaseNotification::query()->whereKey($notification->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'read_at' => $index === 0 ? null : $createdAt->addMinutes(20),
            ]);
        }
    }

    private function alreadyNotified(User $user, NotificationType $type, NotificationPayload $payload): bool
    {
        return $user->notifications()
            ->where('data->type', $type->value)
            ->where('data->subject->id', $payload->subject->id)
            ->exists();
    }

    /**
     * @return list<array{0: NotificationType, 1: NotificationPayload}|null>
     */
    private function asIssuer(int $organizationId): array
    {
        $competitions = Competition::query()->where('organization_id', $organizationId);

        $live = (clone $competitions)->where('status', CompetitionStatus::Live->value)->latest('id')->first();
        $closed = (clone $competitions)->where('status', CompetitionStatus::Closed->value)->latest('id')->first();
        $joined = $live === null ? null : Participant::query()->where('competition_id', $live->id)->with(['organization', 'competition'])->first();

        return [
            $live === null ? null : [NotificationType::OfferReceived, $this->competitionPayload($live, NotificationRoutes::competitionLive($live))],
            $joined === null ? null : [NotificationType::InvitationJoined, $this->competitionPayload($joined->competition, NotificationRoutes::competition($joined->competition), [
                'organization_name' => $joined->organization->name,
                'sponsored' => $joined->entitlement_source === EntitlementSource::SponsoredPass,
            ])],
            $closed === null ? null : [NotificationType::CompetitionClosed, $this->competitionPayload($closed, NotificationRoutes::competition($closed))],
        ];
    }

    /**
     * @return list<array{0: NotificationType, 1: NotificationPayload}|null>
     */
    private function asParticipant(int $organizationId): array
    {
        $invitation = Invitation::query()
            ->where('organization_id', $organizationId)
            ->whereIn('status', [InvitationStatus::Sent->value, InvitationStatus::Viewed->value])
            ->with('competition.organization')
            ->latest('id')
            ->first();
        $live = Participant::query()
            ->where('organization_id', $organizationId)
            ->whereHas('competition', static fn ($query) => $query->where('status', CompetitionStatus::Live->value))
            ->with('competition')
            ->latest('id')
            ->first();
        $award = Award::query()
            ->where('organization_id', $organizationId)
            ->where('status', AwardStatus::Issued->value)
            ->with('competition')
            ->latest('id')
            ->first();

        return [
            $invitation === null ? null : [NotificationType::CompetitionInvited, $this->competitionPayload($invitation->competition, NotificationRoutes::competition($invitation->competition), [
                'issuer_name' => $invitation->competition->organization->name,
                'sponsored' => SponsoredPass::query()
                    ->where('invitation_id', $invitation->id)
                    ->whereIn('status', [PassStatus::Reserved->value, PassStatus::Joined->value])
                    ->exists(),
            ])],
            $live === null ? null : [NotificationType::CompetitionOpened, $this->competitionPayload($live->competition, NotificationRoutes::competitionLive($live->competition))],
            $award === null ? null : [NotificationType::AwardWon, $this->competitionPayload($award->competition, NotificationRoutes::competition($award->competition), [
                'message_to_winner' => $award->message_to_winner,
            ])],
        ];
    }

    /**
     * @return list<array{0: NotificationType, 1: NotificationPayload}|null>
     */
    private function billing(int $organizationId): array
    {
        $subscription = Subscription::query()->where('organization_id', $organizationId)->with('plan')->latest('id')->first();
        $invoice = Invoice::query()->where('organization_id', $organizationId)->latest('id')->first();

        return [
            $subscription === null ? null : [NotificationType::SubscriptionActivated, new NotificationPayload(
                ['plan_name' => $subscription->plan->name, 'ends_at' => Iso::format($subscription->ends_at)],
                NotificationSubject::of($subscription),
                NotificationRoutes::BILLING,
            )],
            $invoice === null ? null : [NotificationType::InvoiceIssued, new NotificationPayload(
                ['number' => $invoice->number],
                NotificationSubject::of($invoice),
                NotificationRoutes::invoice($invoice),
            )],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function competitionPayload(Competition $competition, string $route, array $params = []): NotificationPayload
    {
        return new NotificationPayload(
            [...CompetitionAudience::params($competition), ...$params],
            NotificationSubject::of($competition),
            $route,
        );
    }
}
