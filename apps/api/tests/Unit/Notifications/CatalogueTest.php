<?php

declare(strict_types=1);

use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Enums\DeliveryChannel;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Notifications\BafoNotification;
use Illuminate\Support\Arr;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The catalogue (ARCHITECTURE §11.3) and its copy (§11.4, CONVENTIONS §6): 31 types, the
 * channel matrix, identical AR/EN keys, and the glossary.
 */

/**
 * @return array<string, mixed>
 */
function notificationsLang(string $locale): array
{
    return require lang_path($locale.'/notifications.php');
}

it('has the 31 catalogue types, each with its own notification class', function () {
    expect(NotificationType::cases())->toHaveCount(31);

    foreach (NotificationType::cases() as $type) {
        $class = $type->notificationClass();

        expect(is_subclass_of($class, BafoNotification::class))->toBeTrue()
            ->and($class::type())->toBe($type)
            ->and((new ReflectionClass($class))->isFinal())->toBeTrue();
    }
});

it('follows the §11.3 channel matrix', function (NotificationType $type, array $channels) {
    expect(array_map(static fn (DeliveryChannel $channel): string => $channel->value, $type->channels()))->toBe($channels);
})->with([
    [NotificationType::CompetitionInvited, ['database', 'push']],
    [NotificationType::CompetitionClosingSoon, ['push']],
    [NotificationType::CompetitionClosed, ['database', 'push', 'mail']],
    [NotificationType::CompetitionCancelled, ['database', 'push', 'mail']],
    [NotificationType::StandingLostLead, ['database', 'push']],
    [NotificationType::BafoInvited, ['database', 'push', 'mail']],
    [NotificationType::AwardWon, ['database', 'push', 'mail']],
    [NotificationType::InvitationDeclined, ['database']],
    [NotificationType::SubscriptionActivated, ['database', 'mail']],
    [NotificationType::SubscriptionExpiring, ['database', 'push', 'mail']],
    [NotificationType::InvoiceIssued, ['database', 'mail']],
    [NotificationType::WebhookEndpointDisabled, ['database', 'mail']],
    [NotificationType::ImportFinished, ['database']],
    [NotificationType::ExportFinished, ['database']],
]);

it('gives one fresh lowercase ULID to every instance', function () {
    $class = NotificationType::CompetitionOpened->notificationClass();
    $payload = new NotificationPayload([], new NotificationSubject('competition', 'x'), '/competitions/x');

    $first = $class::fromPayload($payload);
    $second = $class::fromPayload($payload);

    expect($first->id)->toMatch('/^[0-9a-z]{26}$/')
        ->and($first->id)->not->toBe($second->id)
        ->and($first->queue)->toBe('notifications');
});

it('narrows the catalogue channels per recipient, never widens them', function () {
    $class = NotificationType::CompetitionClosed->notificationClass();
    $payload = new NotificationPayload([], new NotificationSubject('competition', 'x'), '/competitions/x');

    expect($class::fromPayload($payload, [DeliveryChannel::Mail])->via(new stdClass))->toBe(['database', 'push'])
        ->and(NotificationType::ImportFinished->notificationClass()::fromPayload($payload, [DeliveryChannel::Push])->via(new stdClass))->toBe(['database']);
});

it('has the same keys in Arabic and English', function () {
    expect(array_keys(Arr::dot(notificationsLang('ar'))))->toEqualCanonicalizing(array_keys(Arr::dot(notificationsLang('en'))));
});

it('has a title and body for every type, and the mail texts for every type that mails', function (string $locale) {
    $lang = notificationsLang($locale);

    foreach (NotificationType::cases() as $type) {
        $keys = ['title', 'body', ...($type->sends(DeliveryChannel::Mail) ? ['mail_subject', 'mail_intro', 'mail_action'] : [])];

        foreach ($keys as $key) {
            expect($lang[$type->key()][$key] ?? null)->toBeString()->not->toBe('', "{$locale}: {$type->key()}.{$key}");
        }
    }
})->with(['ar', 'en']);

it('uses the glossary: no «مناقص» as a noun for people, no old brand, no hype', function () {
    $arabic = implode("\n", Arr::dot(notificationsLang('ar')));
    $english = implode("\n", Arr::dot(notificationsLang('en')));
    $views = collect([
        ...glob(resource_path('views/vendor/mail/*/*.blade.php')) ?: [],
        ...glob(app_path('Modules/Notifications/Resources/views/*/*.blade.php')) ?: [],
    ])->map(fn (string $path): string => (string) file_get_contents($path))->implode("\n");

    foreach ([$arabic, $english, $views] as $text) {
        expect($text)
            ->not->toMatch('/مناقص(?!ة)/u')
            ->not->toMatch('/munaqes|monaqus/i')
            ->not->toContain('أقل سعر')
            ->not->toMatch('/lowest price/i');
    }

    // Brand voice (CONVENTIONS §6.1): no exclamation marks in the copy.
    expect($arabic)->not->toContain('!')
        ->and($english)->not->toContain('!');

    expect($english)->not->toMatch('/\bbids?\b/i')
        ->and($arabic)->toContain('طارح المنافسة')
        ->and($arabic)->toContain('العرض المتصدر')
        ->and($arabic)->toContain('جولة العرض النهائي')
        ->and($arabic)->toContain('رسوم المشاركة مغطّاة')
        ->and($english)->toContain('leading offer')
        ->and($english)->toContain('best-and-final-offer round');
});
