<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The competition invitation e-mail (ARCHITECTURE §11.5): a token-carrying mail sent by
 * Competitions with the shared mail theme. The token travels only in the URL fragment
 * (`{WEB_URL}/{locale}/invitations#t={token}`, D11); the queued payload is encrypted.
 *
 * It never contains amounts (§11.1).
 */
final class CompetitionInvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $plainToken,
        public readonly string $competitionTitle,
        public readonly string $referenceNo,
        public readonly string $direction,
        public readonly string $issuerName,
        public readonly ?string $contactName,
        public readonly ?string $closesAt,
        public readonly bool $sponsored,
        string $mailLocale,
    ) {
        $this->locale($mailLocale);
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->phrase('subject', [
            'competition_type' => $this->competitionType(),
            'competition_title' => $this->competitionTitle,
        ]));
    }

    public function content(): Content
    {
        $base = rtrim($this->webUrl(), '/').'/'.$this->locale.'/invitations#t='.$this->plainToken;

        return new Content(markdown: 'competitions::mail.invitation', with: [
            'greeting' => $this->contactName !== null && $this->contactName !== ''
                ? $this->phrase('greeting_named', ['name' => $this->contactName])
                : $this->phrase('greeting'),
            'intro' => $this->phrase('intro', [
                'issuer_name' => $this->issuerName,
                'competition_type' => $this->competitionType(),
                'competition_title' => $this->competitionTitle,
            ]),
            'reference' => $this->phrase('reference', ['reference' => $this->referenceNo]),
            'closes' => $this->closesAt !== null ? $this->phrase('closes_at', ['close_time' => $this->closesAt]) : null,
            'sponsoredLine' => $this->sponsored ? $this->phrase('sponsored') : null,
            'actionText' => $this->phrase('action'),
            'actionUrl' => $base,
            'declineText' => $this->phrase('decline'),
            'declineUrl' => $base.'&action=decline',
            'outro' => $this->phrase('outro'),
        ]);
    }

    private function competitionType(): string
    {
        $type = __('competitions.direction.'.$this->direction, [], $this->locale);
        $type = is_string($type) ? $type : $this->direction;

        // English sentences use the lowercase word (ARCHITECTURE §11.4).
        return $this->locale === 'en' ? mb_strtolower($type) : $type;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function phrase(string $key, array $replace = []): string
    {
        $text = __('competitions.mail.invitation.'.$key, $replace, $this->locale);

        return is_string($text) ? $text : $key;
    }

    private function webUrl(): string
    {
        $url = config('bafo.platform.web_url', 'http://localhost:3000');

        return is_string($url) ? $url : 'http://localhost:3000';
    }
}
