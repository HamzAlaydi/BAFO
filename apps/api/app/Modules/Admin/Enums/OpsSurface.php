<?php

declare(strict_types=1);

namespace App\Modules\Admin\Enums;

/**
 * Every part of the ops panel (/admin) that the release scope can hide (RELEASE_SCOPE.md §11).
 *
 * Release scope `core` keeps the panel at its minimum: the cases listed in inCore(). Everything
 * else is hidden (not in the navigation, and `canAccess()` false so a direct URL answers 403)
 * and comes back unchanged in scope `full`. Nothing is deleted: the resources, pages, actions
 * and the module Actions behind them stay as they are. The only check is AdminScope::visible().
 * Labels: `admin.enums.ops_surface.<value>`.
 */
enum OpsSurface: string
{
    // Navigation: resources and pages.
    case Dashboard = 'dashboard';
    case Organizations = 'organizations';
    case Users = 'users';
    case AccountDeletions = 'account_deletions';
    case Competitions = 'competitions';
    case Subscriptions = 'subscriptions';
    case Plans = 'plans';
    case Coupons = 'coupons';
    case Vouchers = 'vouchers';
    case Payments = 'payments';
    case Invoices = 'invoices';
    case Sponsorships = 'sponsorships';
    case ApiClients = 'api_clients';
    case WebhookEndpoints = 'webhook_endpoints';
    case Regions = 'regions';
    case Categories = 'categories';
    case CloseReasons = 'close_reasons';
    case Presets = 'presets';
    case LegalDocuments = 'legal_documents';
    case ContactMessages = 'contact_messages';
    case Settings = 'settings';
    case AuditLog = 'audit_log';
    case Admins = 'admins';

    // Inside the pages that stay in core.
    /** Dashboard: payments today, failed e-invoices, sponsorships awaiting a voucher. */
    case DashboardBillingStats = 'dashboard_billing_stats';
    /** Competition view: the Extend header action. */
    case CompetitionExtend = 'competition_extend';
    /** Competition view: Void offer on the ledger, and the void columns. */
    case CompetitionVoidOffer = 'competition_void_offer';
    /** Competition view: Revoke on an invitation. */
    case CompetitionRevokeInvitation = 'competition_revoke_invitation';
    /** Competition view: Grant pass, the sponsored columns and the sponsorship summary. */
    case CompetitionSponsorship = 'competition_sponsorship';
    /** Competition view: the extensions relation manager. */
    case CompetitionExtensions = 'competition_extensions';
    /** Competition view: the rejected-offers relation manager. */
    case CompetitionRejections = 'competition_rejections';
    /** Competition view: the BAFO round rule. */
    case CompetitionBafoRound = 'competition_bafo_round';
    /** Competition view: the final pricing window rule and its timeline rows. */
    case CompetitionFinalWindow = 'competition_final_window';
    /** Competition view: the ERP sync status of an award. */
    case CompetitionErpSync = 'competition_erp_sync';
    /** Organizations: the API and sponsorship switches (the auction switch stays). */
    case OrganizationAdvancedFeatures = 'organization_advanced_features';
    /** Organization members and users: the award / purchase permissions of a team member. */
    case TeamPermissions = 'team_permissions';
    /** Settings: every group except release scope (platform) and the app gate (app). */
    case AdvancedSettings = 'advanced_settings';

    /** Label in the given locale (default: the app locale). Key: `admin.enums.ops_surface.<value>`. */
    public function label(?string $locale = null): string
    {
        $label = __('admin.enums.ops_surface.'.$this->value, [], $locale);

        return is_string($label) ? $label : $this->value;
    }

    /**
     * Shown in release scope `core`. Every other case is shown in `full` only.
     */
    public function inCore(): bool
    {
        return match ($this) {
            self::Dashboard,
            self::Organizations,
            self::Users,
            self::Competitions,
            self::Subscriptions,
            self::Settings => true,
            default => false,
        };
    }

    /**
     * The cases shown in `core`, in declaration order.
     *
     * @return list<self>
     */
    public static function core(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $surface): bool => $surface->inCore()));
    }
}
