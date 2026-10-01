<?php

declare(strict_types=1);

use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Services\NotificationRenderer;
use App\Modules\Notifications\Support\DisplayTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

/*
 * Template rendering (ARCHITECTURE §11.4, CONVENTIONS §9): direction words, Riyadh time,
 * Arabic and English plurals, money, the fees-covered line and the unspecified reason.
 */

beforeEach(function () {
    $this->renderer = app(NotificationRenderer::class);
    $this->title = ['competition_title' => 'بيع خردة حديد', 'direction' => 'auction'];
});

it('names the competition type from its direction, lowercase inside English sentences', function () {
    expect($this->renderer->title(NotificationType::CompetitionInvited, $this->title, 'ar'))->toBe('دعوة للمشاركة في مزايدة')
        ->and($this->renderer->title(NotificationType::CompetitionInvited, $this->title, 'en'))->toBe('New auction invitation')
        ->and($this->renderer->title(NotificationType::CompetitionInvited, ['direction' => 'tender'], 'en'))->toBe('New tender invitation');
});

it('adds the fees-covered line only when the invitation is sponsored', function () {
    $params = [...$this->title, 'issuer_name' => 'شركة المصدر'];

    expect($this->renderer->body(NotificationType::CompetitionInvited, [...$params, 'sponsored' => true], 'ar'))
        ->toBe('تدعوك شركة المصدر للمشاركة في «بيع خردة حديد». رسوم المشاركة مغطّاة.')
        ->and($this->renderer->body(NotificationType::CompetitionInvited, [...$params, 'sponsored' => false], 'en'))
        ->toBe('شركة المصدر invites you to take part in “بيع خردة حديد”.')
        ->and($this->renderer->body(NotificationType::InvitationJoined, [...$params, 'organization_name' => 'مورد أ', 'sponsored' => true], 'en'))
        ->toBe('مورد أ joined “بيع خردة حديد”. Participation fees are covered.');
});

it('shows times in Riyadh with Western digits and a 12-hour clock', function (string $iso, string $ar, string $en) {
    expect(DisplayTime::formatIso($iso, 'ar'))->toBe($ar)
        ->and(DisplayTime::formatIso($iso, 'en'))->toBe($en)
        ->and(DisplayTime::format(CarbonImmutable::parse($iso), 'en'))->toBe($en);
})->with([
    ['2026-11-09T12:30:00.000Z', '9 نوفمبر 2026، 3:30 م', '9 Nov 2026, 3:30 PM'],
    ['2026-01-31T21:05:00.000Z', '1 فبراير 2026، 12:05 ص', '1 Feb 2026, 12:05 AM'],
    ['2026-06-15T06:00:00.000Z', '15 يونيو 2026، 9:00 ص', '15 Jun 2026, 9:00 AM'],
]);

it('fills time placeholders in the reader\'s language', function () {
    $params = [...$this->title, 'close_time' => '2026-11-09T12:30:00.000Z'];

    expect($this->renderer->body(NotificationType::CompetitionExtended, $params, 'ar'))->toBe('مُدّد وقت إغلاق «بيع خردة حديد» إلى 9 نوفمبر 2026، 3:30 م.')
        ->and($this->renderer->body(NotificationType::CompetitionExtended, $params, 'en'))->toBe('The closing time of “بيع خردة حديد” was extended to 9 Nov 2026, 3:30 PM.');
});

it('uses the Arabic plural forms for minutes and days', function (int $count, string $minutes, string $days) {
    expect($this->renderer->body(NotificationType::CompetitionClosingSoon, [...$this->title, 'minutes' => $count], 'ar'))->toBe($minutes)
        ->and($this->renderer->body(NotificationType::SubscriptionExpiring, ['days_left' => $count], 'ar'))->toBe($days);
})->with([
    [1, 'تُغلق «بيع خردة حديد» خلال دقيقة.', 'ينتهي اشتراكك غداً.'],
    [2, 'تُغلق «بيع خردة حديد» خلال دقيقتين.', 'ينتهي اشتراكك خلال يومين.'],
    [7, 'تُغلق «بيع خردة حديد» خلال 7 دقائق.', 'ينتهي اشتراكك خلال 7 أيام.'],
    [15, 'تُغلق «بيع خردة حديد» خلال 15 دقيقة.', 'ينتهي اشتراكك خلال 15 يوماً.'],
]);

it('uses the English plural forms', function () {
    expect($this->renderer->body(NotificationType::CompetitionClosingSoon, [...$this->title, 'minutes' => 1], 'en'))->toBe('“بيع خردة حديد” closes in 1 minute.')
        ->and($this->renderer->body(NotificationType::CompetitionClosingSoon, [...$this->title, 'minutes' => 10], 'en'))->toBe('“بيع خردة حديد” closes in 10 minutes.')
        ->and($this->renderer->body(NotificationType::SubscriptionExpiring, ['days_left' => 1], 'en'))->toBe('Your subscription ends tomorrow.')
        ->and($this->renderer->body(NotificationType::SubscriptionExpiring, ['days_left' => 3], 'en'))->toBe('Your subscription ends in 3 days.');
});

it('formats money with the §9.2 rules and picks translated names', function () {
    $voucher = ['code' => 'V-7K2M9Q4XZA', 'amount_minor' => 1_250_000, 'currency' => 'SAR'];
    $plan = ['plan_name' => ['ar' => 'باقة برو', 'en' => 'Pro'], 'ends_at' => '2026-11-09T12:30:00.000Z'];

    expect($this->renderer->body(NotificationType::VoucherIssued, $voucher, 'ar'))->toBe('أُضيفت القسيمة V-7K2M9Q4XZA بقيمة 12,500.00 ر.س إلى حسابك.')
        ->and($this->renderer->body(NotificationType::VoucherIssued, $voucher, 'en'))->toBe('Voucher V-7K2M9Q4XZA worth SAR 12,500.00 was added to your account.')
        ->and($this->renderer->body(NotificationType::SubscriptionActivated, $plan, 'ar'))->toBe('تم تفعيل باقة برو حتى 9 نوفمبر 2026، 3:30 م.')
        ->and($this->renderer->body(NotificationType::SubscriptionActivated, $plan, 'en'))->toBe('Your Pro plan is active until 9 Nov 2026, 3:30 PM.');
});

it('states the reason, or that none was given', function () {
    $reason = ['ar' => 'تغيّر الاحتياج', 'en' => 'The requirement changed'];

    expect($this->renderer->body(NotificationType::CompetitionCancelled, [...$this->title, 'reason' => $reason], 'en'))->toBe('“بيع خردة حديد” was cancelled. Reason: The requirement changed')
        ->and($this->renderer->body(NotificationType::CompetitionCancelled, [...$this->title, 'reason' => null], 'ar'))->toBe('أُلغيت «بيع خردة حديد». السبب: غير محدد')
        ->and($this->renderer->body(NotificationType::AwardRevoked, [...$this->title, 'reason' => 'لم تُستكمل المتطلبات'], 'en'))->toBe('The issuer revoked the award of “بيع خردة حديد”. Reason: لم تُستكمل المتطلبات');
});

it('renders the mail texts', function () {
    $mail = $this->renderer->mail(NotificationType::InvoiceIssued, ['number' => 'BAFO-INV-2026-000123'], 'ar');

    expect($mail->subject)->toBe('فاتورة ضريبية جديدة BAFO-INV-2026-000123')
        ->and($mail->intro)->toBe('أُصدرت الفاتورة BAFO-INV-2026-000123.')
        ->and($mail->action)->toBe('فتح الفاتورة');
});
