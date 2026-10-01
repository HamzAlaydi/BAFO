<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mail;

use App\Modules\Identity\Enums\OrgRole;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\App;

/**
 * The team invitation mail (ARCHITECTURE §11.5, §13.10). The link carries the one-time token in
 * the URL fragment only: `{WEB_URL}/{locale}/auth/accept-invite#t={token}` (D11); the queued
 * payload is encrypted (SECURITY_REVIEW S-08).
 */
final class TeamInvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $inviteeName,
        public readonly string $organizationName,
        public readonly string $inviterName,
        public readonly OrgRole $role,
        public readonly string $acceptUrl,
        public readonly CarbonImmutable $expiresAt,
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::lang('identity.mail.team_invitation.subject', [
            'organization' => $this->organizationName,
        ]));
    }

    public function content(): Content
    {
        // content() runs inside Mailable::withLocale(), so the app locale is the mail's locale.
        $locale = App::getLocale();

        return new Content(markdown: 'identity::mail.team-invitation', with: [
            'greeting' => self::lang('identity.mail.team_invitation.greeting', ['name' => $this->inviteeName]),
            'intro' => self::lang('identity.mail.team_invitation.intro', [
                'inviter' => $this->inviterName,
                'organization' => $this->organizationName,
                'role' => $this->role->label($locale),
            ]),
            'action' => self::lang('identity.mail.team_invitation.action'),
            'url' => $this->acceptUrl,
            'expires' => self::lang('identity.mail.team_invitation.expires', [
                'date' => $this->expiresAt->setTimezone('Asia/Riyadh')->locale($locale)->translatedFormat('j F Y'),
            ]),
            'ignore' => self::lang('identity.mail.team_invitation.ignore'),
        ]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function lang(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
