<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Features\Feature;
use App\Support\Features\FeatureFlags;
use App\Support\Http\Iso;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Date;

/**
 * Assembles the AppConfig resource of API.md §2.13: the runtime settings (§15.3), the
 * realtime connection, the current legal versions, the release-scope feature flags
 * (RELEASE_SCOPE.md §1.4) and the platform constants.
 */
final readonly class AppConfigBuilder
{
    public function __construct(private Settings $settings, private FeatureFlags $features) {}

    /**
     * @return array<string, mixed>
     */
    public function build(string $locale): array
    {
        $maintenanceMessage = $this->settings->get('app.maintenance.message', []);
        $flags = $this->features->all();

        return [
            'min_version' => [
                'ios' => $this->string('app.min_version.ios'),
                'android' => $this->string('app.min_version.android'),
            ],
            'latest_version' => [
                'ios' => $this->string('app.latest_version.ios'),
                'android' => $this->string('app.latest_version.android'),
            ],
            'store_links' => $this->object('app.store_links', ['ios', 'android']),
            'maintenance' => [
                'enabled' => $this->settings->get('app.maintenance.enabled', false) === true,
                'message' => is_array($maintenanceMessage) && is_string($maintenanceMessage[$locale] ?? null)
                    ? $maintenanceMessage[$locale]
                    : '',
            ],
            'support' => $this->object('app.support', ['email', 'phone', 'whatsapp']),
            'realtime' => [
                'key' => (string) config('bafo.platform.realtime.key'),
                'host' => (string) config('bafo.platform.realtime.host'),
                'port' => (int) config('bafo.platform.realtime.port'),
                'scheme' => (string) config('bafo.platform.realtime.scheme'),
            ],
            'legal' => $this->legalVersions($locale),
            'features' => [
                // RELEASE_SCOPE.md §1.4: the scope is for display only; clients branch on `flags.<name>`.
                'release_scope' => $this->features->scope()->value,
                // Kept for compatibility: always equals flags.sponsorship (scope and Billing's global
                // switch `sponsorship.enabled`, §15.3); the per-organization flag is separate.
                'sponsorship' => $flags[Feature::Sponsorship->value],
                'flags' => $flags,
            ],
            'currency' => 'SAR',
            'vat_rate_bp' => (int) config('bafo.billing.vat_rate_bp', 1500),
            'supported_locales' => array_values((array) config('app.supported_locales', ['ar', 'en'])),
            'server_time' => Iso::format(Date::now()),
        ];
    }

    /**
     * {"terms": {"version": "2026-10-01"}, ...}: the latest published version in the request
     * locale, or null while none is published.
     *
     * @return array<string, array{version: string}|null>
     */
    private function legalVersions(string $locale): array
    {
        $legal = [];

        foreach (LegalDocumentCode::announcedInAppConfig() as $code) {
            $document = LegalDocument::latestPublished($code, $locale);
            $legal[$code->value] = $document === null ? null : ['version' => $document->version];
        }

        return $legal;
    }

    private function string(string $key): string
    {
        $value = $this->settings->get($key, '');

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * The setting as an object with exactly the given string keys.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function object(string $key, array $keys): array
    {
        $value = $this->settings->get($key, []);
        $value = is_array($value) ? $value : [];
        $object = [];

        foreach ($keys as $field) {
            $object[$field] = is_scalar($value[$field] ?? null) ? (string) $value[$field] : '';
        }

        return $object;
    }
}
