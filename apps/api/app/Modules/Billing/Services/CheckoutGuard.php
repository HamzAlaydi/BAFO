<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Identity\Models\Organization;
use App\Support\Auth\Actor;
use App\Support\Auth\Channel;
use App\Support\Exceptions\ApiException;

/**
 * The checkout preconditions of ARCHITECTURE §13.1 that follow the permission check:
 *
 *   2. `X-Platform` is web or absent       → 403 purchase_not_available_on_platform
 *   3. the billing profile is complete      → 422 billing_profile_incomplete (errors: the fields)
 *   4. `return_url` is allow-listed         → 422 return_url_not_allowed
 */
final class CheckoutGuard
{
    /**
     * API field paths of the organization columns (API.md §2.2 `billing_profile_missing`).
     */
    private const array FIELD_PATHS = [
        'address_building_number' => 'national_address.building_number',
        'address_street' => 'national_address.street',
        'address_district' => 'national_address.district',
        'address_postal_code' => 'national_address.postal_code',
    ];

    public function check(Actor $actor, Organization $organization, string $returnUrl): void
    {
        $this->ensurePlatform($actor);
        $this->ensureBillingProfile($organization);
        $this->ensureReturnUrl($returnUrl);
    }

    public function ensurePlatform(Actor $actor): void
    {
        if (in_array($actor->channel, [Channel::Ios, Channel::Android], true)) {
            throw new ApiException('purchase_not_available_on_platform', 'billing.errors.purchase_not_available_on_platform', 403);
        }
    }

    public function ensureBillingProfile(Organization $organization): void
    {
        $missing = $organization->missingBillingProfileFields();

        if ($missing === []) {
            return;
        }

        $errors = [];

        foreach ($missing as $column) {
            $path = self::FIELD_PATHS[$column] ?? $column;
            $message = __('billing.validation.billing_profile_field_missing', ['attribute' => $this->attribute($column)]);
            $errors[$path] = [is_string($message) ? $message : $path];
        }

        throw new ApiException(
            'billing_profile_incomplete',
            'billing.errors.billing_profile_incomplete',
            422,
            errors: $errors,
            details: ['missing' => array_keys($errors)],
        );
    }

    /**
     * CONTRACT-GAP: §13.1 says "starts with". A plain prefix test would accept
     * `http://localhost:3000.evil.test`, so the scheme, host and port must match exactly and
     * the path must start with the allowed path.
     */
    public function ensureReturnUrl(string $returnUrl): void
    {
        /** @var list<string> $allowed */
        $allowed = (array) config('bafo.billing.allowed_return_urls', []);
        $candidate = parse_url($returnUrl);

        if (is_array($candidate) && isset($candidate['scheme'], $candidate['host']) && ! isset($candidate['user']) && ! isset($candidate['pass'])) {
            foreach ($allowed as $prefix) {
                if ($this->matches($candidate, $prefix)) {
                    return;
                }
            }
        }

        throw new ApiException('return_url_not_allowed', 'billing.errors.return_url_not_allowed', 422, errors: [
            'return_url' => [$this->message('billing.errors.return_url_not_allowed')],
        ]);
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}  $candidate
     */
    private function matches(array $candidate, string $prefix): bool
    {
        $allowed = parse_url(trim($prefix));

        if (! is_array($allowed) || ! isset($allowed['scheme'], $allowed['host'])) {
            return false;
        }

        $sameOrigin = strtolower($candidate['scheme'] ?? '') === strtolower($allowed['scheme'])
            && strtolower($candidate['host'] ?? '') === strtolower($allowed['host'])
            && ($candidate['port'] ?? null) === ($allowed['port'] ?? null);

        $allowedPath = rtrim($allowed['path'] ?? '', '/');
        $path = $candidate['path'] ?? '';

        return $sameOrigin && ($allowedPath === '' || $path === $allowedPath || str_starts_with($path, $allowedPath.'/'));
    }

    private function attribute(string $column): string
    {
        $label = __('billing.attributes.'.$column);

        return is_string($label) ? $label : $column;
    }

    private function message(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
