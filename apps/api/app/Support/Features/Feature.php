<?php

declare(strict_types=1);

namespace App\Support\Features;

/**
 * The feature flags of RELEASE_SCOPE.md §1.3, in catalogue order. The value is the key clients
 * read in `GET /app-config` → `features.flags` and the parameter of the `feature:<flag>` route
 * middleware. How each one derives from the release scope is FeatureFlags::enabled().
 *
 * Reserved cases (deletion approval, deleted list, offer report, log in as, Google sign-in) name
 * features that are not built: they are `false` in both scopes so clients have one lookup path.
 */
enum Feature: string
{
    case TeamManagement = 'team_management';
    case VendorDirectory = 'vendor_directory';
    case IntegrationsApi = 'integrations_api';
    case CsvImportExport = 'csv_import_export';
    case Sponsorship = 'sponsorship';
    case BafoRound = 'bafo_round';
    case SealedFormat = 'sealed_format';
    case AdvancedRules = 'advanced_rules';
    case FinalPricingWindow = 'final_pricing_window';
    case DeletionApproval = 'deletion_approval';
    case DeletedCompetitions = 'deleted_competitions';
    case OfferReport = 'offer_report';
    case LoginAs = 'login_as';
    case GoogleSignin = 'google_signin';
    case DarkMode = 'dark_mode';
    case BillingInvoices = 'billing_invoices';
    case CustomPlanQuote = 'custom_plan_quote';
    case Coupons = 'coupons';
    case QaComments = 'qa_comments';
    case Attachments = 'attachments';
    case ExtendCompetition = 'extend_competition';
    case CancelCompetition = 'cancel_competition';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $feature): string => $feature->value, self::cases());
    }
}
