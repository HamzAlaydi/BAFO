<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Enums\NotificationType;
use Carbon\CarbonImmutable;

/**
 * Illustrative payloads of every catalogue type, for the local mail preview. Nothing here is
 * stored or sent.
 */
final class SampleNotifications
{
    private const string COMPETITION_ID = '01j9zq4m1x2a3b4c5d6e7f8g9h';

    public static function payload(NotificationType $type): NotificationPayload
    {
        $competition = new NotificationSubject('competition', self::COMPETITION_ID);
        $competitionRoute = '/competitions/'.self::COMPETITION_ID;
        $base = ['competition_title' => 'توريد أجهزة حاسب محمولة', 'direction' => 'tender'];
        $at = CarbonImmutable::parse('2026-11-09T12:30:00Z')->format('Y-m-d\TH:i:s.v\Z');

        $params = match ($type) {
            NotificationType::CompetitionInvited => [...$base, 'issuer_name' => 'شركة المصدر', 'sponsored' => true],
            NotificationType::CompetitionFinalWindowStarted, NotificationType::CompetitionExtended => [...$base, 'close_time' => $at],
            NotificationType::CompetitionClosingSoon => [...$base, 'minutes' => 10],
            NotificationType::CompetitionCancelled => [...$base, 'reason' => ['ar' => 'تغيّر الاحتياج', 'en' => 'The requirement changed']],
            NotificationType::BafoInvited => [...$base, 'cutoff_time' => $at],
            NotificationType::AwardWon => [...$base, 'message_to_winner' => 'نرجو التواصل مع إدارة المشتريات لاستكمال أمر الشراء.'],
            NotificationType::AwardRevoked => [...$base, 'reason' => 'تعذّر استكمال متطلبات التعاقد.'],
            NotificationType::InvitationJoined => [...$base, 'organization_name' => 'مورد أ', 'sponsored' => false],
            NotificationType::InvitationDeclined => [...$base, 'organization_name' => 'مورد ب'],
            NotificationType::SubscriptionActivated, NotificationType::SubscriptionExpired => ['plan_name' => ['ar' => 'باقة برو', 'en' => 'Pro'], 'ends_at' => $at],
            NotificationType::SubscriptionExpiring => ['plan_name' => ['ar' => 'باقة برو', 'en' => 'Pro'], 'ends_at' => $at, 'days_left' => 3],
            NotificationType::InvoiceIssued => ['number' => 'BAFO-INV-2026-000123'],
            NotificationType::SponsorshipUnusedPasses => [...$base, 'unused_count' => 2],
            NotificationType::SponsorshipPublishFailed => [...$base, 'error_code' => 'min_participants_not_met'],
            NotificationType::VoucherIssued => ['code' => 'V-7K2M9Q4XZA', 'amount_minor' => 40000, 'currency' => 'SAR'],
            NotificationType::WebhookEndpointDisabled => ['url' => 'https://erp.example.sa/bafo/webhooks'],
            NotificationType::ImportFinished => ['mode' => 'commit', 'status' => 'completed', 'created' => 12, 'updated' => 3, 'errors' => 1],
            NotificationType::ExportFinished => ['export_type' => 'results', 'format' => 'xlsx', 'status' => 'completed'],
            default => $base,
        };

        [$subject, $route] = match ($type) {
            NotificationType::SubscriptionActivated,
            NotificationType::SubscriptionExpiring,
            NotificationType::SubscriptionExpired => [new NotificationSubject('subscription', self::COMPETITION_ID), NotificationRoutes::BILLING],
            NotificationType::PaymentFailed => [new NotificationSubject('payment', self::COMPETITION_ID), NotificationRoutes::BILLING],
            NotificationType::VoucherIssued => [new NotificationSubject('coupon', self::COMPETITION_ID), NotificationRoutes::BILLING],
            NotificationType::InvoiceIssued => [new NotificationSubject('invoice', self::COMPETITION_ID), NotificationRoutes::BILLING.'/invoices/'.self::COMPETITION_ID],
            NotificationType::WebhookEndpointDisabled => [new NotificationSubject('webhook_endpoint', self::COMPETITION_ID), NotificationRoutes::INTEGRATIONS],
            NotificationType::ImportFinished => [new NotificationSubject('import_job', self::COMPETITION_ID), NotificationRoutes::INTEGRATIONS],
            NotificationType::ExportFinished => [new NotificationSubject('export_job', self::COMPETITION_ID), NotificationRoutes::INTEGRATIONS],
            default => [$competition, $competitionRoute],
        };

        return new NotificationPayload($params, $subject, $route);
    }
}
