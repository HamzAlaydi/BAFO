<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mail;

use App\Modules\Identity\Enums\OtpPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The one-time code mail (ARCHITECTURE §11.5): e-mail verification, password reset or
 * invitation claim. Queued on `mail` after the surrounding transaction commits. The code is
 * never logged by the application, and the queued payload is encrypted (SECURITY_REVIEW S-08).
 */
final class OtpCodeMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly OtpPurpose $purpose,
        public readonly int $ttlMinutes,
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::lang('identity.mail.otp.subject.'.$this->purpose->value));
    }

    public function content(): Content
    {
        return new Content(markdown: 'identity::mail.otp-code', with: [
            'heading' => self::lang('identity.mail.otp.heading'),
            'intro' => self::lang('identity.mail.otp.intro.'.$this->purpose->value),
            'code' => $this->code,
            'expires' => self::lang('identity.mail.otp.expires', ['minutes' => (string) $this->ttlMinutes]),
            'ignore' => self::lang('identity.mail.otp.ignore'),
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
