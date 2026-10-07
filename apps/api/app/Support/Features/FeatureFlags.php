<?php

declare(strict_types=1);

namespace App\Support\Features;

use App\Support\Settings\Settings;
use InvalidArgumentException;

/**
 * Release-scope feature flags (RELEASE_SCOPE.md §1): the one place on the server where the scope
 * setting `platform.release_scope` turns into per-feature booleans.
 *
 *   app(FeatureFlags::class)->scope()                     ReleaseScope::Core | Full
 *   app(FeatureFlags::class)->enabled(Feature::BafoRound) bool
 *   app(FeatureFlags::class)->enabled('bafo_round')       same, by flag name
 *   app(FeatureFlags::class)->all()                       ['team_management' => false, ...] in catalogue order
 *
 * Only the HTTP edge consults it: the `feature:<flag>` route middleware (EnsureFeatureEnabled),
 * FormRequests, Resources and lookup queries. Actions, jobs, listeners, notifications and the
 * admin panel never do, so existing records keep rendering and the admin stays complete.
 */
final readonly class FeatureFlags
{
    public const string SETTING_KEY = 'platform.release_scope';

    public function __construct(private Settings $settings) {}

    public function scope(): ReleaseScope
    {
        return ReleaseScope::fromSetting($this->settings->get(self::SETTING_KEY, ReleaseScope::Core->value));
    }

    /**
     * The derivation table of RELEASE_SCOPE.md §1.3.
     *
     * @throws InvalidArgumentException for a flag name that is not in the catalogue
     */
    public function enabled(Feature|string $feature): bool
    {
        $feature = $feature instanceof Feature ? $feature : self::feature($feature);
        $full = $this->scope()->isFull();

        return match ($feature) {
            Feature::TeamManagement,
            Feature::VendorDirectory,
            Feature::IntegrationsApi,
            Feature::CsvImportExport,
            Feature::BafoRound,
            Feature::SealedFormat,
            Feature::AdvancedRules,
            Feature::FinalPricingWindow,
            Feature::DarkMode,
            Feature::BillingInvoices,
            Feature::CustomPlanQuote,
            Feature::Coupons,
            Feature::ExtendCompetition => $full,

            // Billing's global switch (ARCHITECTURE §15.3, default true) still applies in `full`.
            Feature::Sponsorship => $full && $this->settings->get('sponsorship.enabled', true) === true,

            // Reserved: not built (ARCHITECTURE §18); off in both scopes until they ship.
            Feature::DeletionApproval,
            Feature::DeletedCompetitions,
            Feature::OfferReport,
            Feature::LoginAs,
            Feature::GoogleSignin => false,

            // Core in both scopes.
            Feature::QaComments,
            Feature::Attachments,
            Feature::CancelCompetition => true,
        };
    }

    /**
     * Every flag, keyed by name, in the order of the catalogue (`features.flags` of GET /app-config).
     *
     * @return array<string, bool>
     */
    public function all(): array
    {
        $flags = [];

        foreach (Feature::cases() as $feature) {
            $flags[$feature->value] = $this->enabled($feature);
        }

        return $flags;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function feature(string $name): Feature
    {
        return Feature::tryFrom($name)
            ?? throw new InvalidArgumentException("Unknown feature flag [{$name}]; see App\\Support\\Features\\Feature.");
    }
}
