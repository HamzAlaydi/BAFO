<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\InvitationSent;
use App\Modules\Competitions\Mail\CompetitionInvitationMail;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;

/**
 * Sends invitations (ARCHITECTURE §6.2 `→ sent`, §13.11): a new one-time token (40 random bytes,
 * base64url; only its sha256 is stored), the token-carrying mail (queued after commit), and
 * InvitationSent. Resending issues a new token, which invalidates the old one.
 */
final readonly class InvitationSender
{
    public function __construct(private AccessPolicy $access) {}

    /**
     * @param  Collection<int, Invitation>  $invitations
     */
    public function sendAll(Competition $competition, Collection $invitations, Actor $actor, string $auditAction = 'invitation.sent'): void
    {
        if ($invitations->isEmpty()) {
            return;
        }

        $competition->loadMissing('organization');
        $invitations->each(static fn (Invitation $invitation): Invitation => $invitation->setRelation('competition', $competition));
        $coverage = $this->access->coverageFor($invitations);

        foreach ($invitations as $invitation) {
            $this->send($competition, $invitation, $actor, ($coverage[$invitation->id] ?? null) === Coverage::Sponsored, $auditAction);
        }
    }

    public function send(Competition $competition, Invitation $invitation, Actor $actor, bool $sponsored, string $auditAction = 'invitation.sent'): string
    {
        $token = self::newToken();
        $now = Date::now();

        $invitation->forceFill([
            'status' => $invitation->status === InvitationStatus::Draft ? InvitationStatus::Sent : $invitation->status,
            'token_hash' => self::hash($token),
            'sent_at' => $now,
        ])->save();

        // CONTRACT-GAP: the mail goes in the invited user's language when the e-mail belongs to a
        // user, otherwise in Arabic (the default language).
        $user = User::query()->where('email', $invitation->email)->first();
        $locale = $user !== null && in_array($user->locale, ['ar', 'en'], true) ? $user->locale : 'ar';

        Mail::to($invitation->email)->queue(new CompetitionInvitationMail(
            plainToken: $token,
            competitionTitle: $competition->title,
            referenceNo: (string) $competition->reference_no,
            direction: $competition->direction->value,
            issuerName: $competition->organization->name,
            contactName: $invitation->name,
            closesAt: $competition->scheduled_close_at !== null ? DisplayTime::format($competition->effective_close_at ?? $competition->scheduled_close_at, $locale) : null,
            sponsored: $sponsored,
            mailLocale: $locale,
        ));

        AuditLogger::log($auditAction, $invitation, meta: ['competition_id' => $competition->public_id], actor: $actor,
            organizationId: $competition->organization_id);

        event(new InvitationSent($invitation, $actor));

        return $token;
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
