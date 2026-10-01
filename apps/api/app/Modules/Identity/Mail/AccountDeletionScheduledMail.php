<?php

declare(strict_types=1);

namespace App\Modules\Identity\Mail;

use App\Modules\Identity\Enums\DeletionScope;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\App;

/**
 * "Account deletion scheduled" (ARCHITECTURE §11.5, §13.8): when the deletion runs and how to
 * cancel it before then.
 */
final class AccountDeletionScheduledMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly DeletionScope $scope,
        public readonly CarbonImmutable $scheduledFor,
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: self::lang('identity.mail.account_deletion.subject'));
    }

    public function content(): Content
    {
        // content() runs inside Mailable::withLocale(), so the app locale is the mail's locale.
        $locale = App::getLocale();

        return new Content(markdown: 'identity::mail.account-deletion-scheduled', with: [
            'greeting' => self::lang('identity.mail.account_deletion.greeting', ['name' => $this->name]),
            'intro' => self::lang('identity.mail.account_deletion.intro.'.$this->scope->value, [
                'date' => $this->scheduledFor->setTimezone('Asia/Riyadh')->locale($locale)->translatedFormat('j F Y'),
            ]),
            'cancel' => self::lang('identity.mail.account_deletion.cancel'),
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
