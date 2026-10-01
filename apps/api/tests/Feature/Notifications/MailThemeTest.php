<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Notifications\CompetitionCancelledNotification;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Traits\Localizable;

/*
 * The shared BAFO mail theme (resources/views/vendor/mail): brand header and colours, RTL for
 * Arabic, localised footer. It applies to every markdown mail, including the token-carrying
 * mails other modules send (§11.5).
 */

function renderInLocale(string $locale, Closure $render): string
{
    $helper = new class
    {
        use Localizable;

        public function run(string $locale, Closure $render): string
        {
            return $this->withLocale($locale, $render);
        }
    };

    return $helper->run($locale, $render);
}

it('themes any markdown mail right to left in Arabic, with the BAFO header and footer', function () {
    $html = renderInLocale('ar', fn (): string => (string) (new MailMessage)
        ->subject('رمز التحقق')
        ->line('رمز التحقق الخاص بك صالح لمدة 10 دقائق.')
        ->action('تأكيد البريد', 'http://localhost:3000/ar/auth/verify')
        ->render());

    expect($html)->toContain('lang="ar" dir="rtl"')
        ->toContain('<body dir="rtl"')
        ->toContain('text-align: right')
        ->toContain('src="http://localhost:8000/mail/brand/bafo-mark.png"')
        ->toContain('alt="بافو"')
        ->toContain('href="http://localhost:3000/ar"')
        ->toContain('تصلك هذه الرسالة لأن لديك حساباً في منصة بافو.')
        ->toContain('© '.date('Y').' بافو. جميع الحقوق محفوظة.')
        ->toContain('background-color: #0E9F6E')
        ->not->toContain('Laravel');
});

it('themes the same mail left to right in English', function () {
    $html = renderInLocale('en', fn (): string => (string) (new MailMessage)->line('Your code is valid for 10 minutes.')->render());

    expect($html)->toContain('lang="en" dir="ltr"')
        ->toContain('text-align: left')
        ->toContain('alt="BAFO"')
        ->toContain('href="http://localhost:3000/en"')
        ->toContain('© '.date('Y').' BAFO. All rights reserved.');
});

it('writes the plain-text part without HTML', function () {
    $text = renderInLocale('ar', fn (): string => (string) app(Markdown::class)->renderText('notifications::mail.notification', [
        'greeting' => 'مرحباً سارة،', 'intro' => 'أُغلقت المنافسة.', 'lines' => [], 'actionText' => 'عرض المنافسة',
        'actionUrl' => 'http://localhost:3000/ar/dashboard/competitions/x', 'salutation' => 'مع التحية،', 'signature' => 'فريق بافو',
    ]));

    expect($text)->toContain('مرحباً سارة،')
        ->toContain('عرض المنافسة: http://localhost:3000/ar/dashboard/competitions/x')
        ->not->toContain('<p>');
});

it('escapes user-entered values: a competition title can never become a link in a mail', function () {
    $notification = CompetitionCancelledNotification::fromPayload(new NotificationPayload(
        ['competition_title' => '[اضغط هنا](https://evil.example) **عاجل** <script>x</script>', 'direction' => 'tender', 'reason' => null],
        new NotificationSubject('competition', '01j9zq4m1x2a3b4c5d6e7f8g9h'),
        '/competitions/01j9zq4m1x2a3b4c5d6e7f8g9h',
    ));
    $user = new User(['name' => 'سارة', 'locale' => 'ar']);

    $html = renderInLocale('ar', fn (): string => (string) $notification->toMail($user)->render());

    expect($html)->not->toContain('href="https://evil.example"')
        ->not->toContain('<strong>')
        ->not->toContain('<script>')
        ->toContain('[اضغط هنا](https://evil.example) **عاجل** &lt;script&gt;');
});
