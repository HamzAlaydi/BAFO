<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\CompetitionSettings;
use App\Modules\Competitions\Services\InvitationSender;
use App\Modules\Competitions\Services\StateMachine;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * `POST …/invitations/{invitation}/resend` (API.md §1.4): a sent or viewed invitation, before
 * the cut-off, gets a new token (the old one stops working) and the mail again. At most
 * 3 per invitation per day (429 `too_many_requests`).
 */
final readonly class ResendInvitation
{
    public function __construct(
        private InvitationSender $sender,
        private AccessPolicy $access,
        private CompetitionSettings $settings,
    ) {}

    public function handle(Invitation $invitation, Actor $actor): void
    {
        DB::transaction(function () use ($invitation, $actor): void {
            $locked = Invitation::query()->with('competition.organization')->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            $competition = $locked->competition;

            if (! in_array($locked->status, [InvitationStatus::Sent, InvitationStatus::Viewed], true)) {
                throw StateMachine::invalidInvitation($locked->status, InvitationStatus::Sent);
            }

            if ($competition->invitation_cutoff_at === null || Date::now()->greaterThanOrEqualTo($competition->invitation_cutoff_at)) {
                throw new ApiException(
                    errorCode: 'invitation_cutoff_passed',
                    messageKey: 'competitions.errors.invitation_cutoff_passed',
                    status: 409,
                );
            }

            $key = 'invitation-resend:'.$locked->id;
            $limit = $this->settings->resendPerDay();

            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $retryAfter = RateLimiter::availableIn($key);

                throw new ApiException(
                    errorCode: 'too_many_requests',
                    status: 429,
                    headers: ['Retry-After' => (string) $retryAfter],
                    details: ['retry_after_seconds' => $retryAfter],
                );
            }

            RateLimiter::hit($key, 86_400);

            $coverage = $this->access->coverageFor([$locked]);

            $this->sender->send($competition, $locked, $actor, ($coverage[$locked->id] ?? null) === Coverage::Sponsored, 'invitation.resent');
        });
    }
}
