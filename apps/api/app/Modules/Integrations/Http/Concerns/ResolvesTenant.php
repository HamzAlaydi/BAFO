<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Concerns;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Data\ApiClientContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * The tenant of a request: the signed-in user's organization (app v1) or the API client's
 * organization (public v1, ARCHITECTURE §8.6). Every lookup is scoped to it explicitly.
 */
trait ResolvesTenant
{
    protected function organization(Request $request): Organization
    {
        $context = $request->attributes->get(ApiClientContext::ATTRIBUTE);

        if ($context instanceof ApiClientContext) {
            $context->client->loadMissing('organization');

            return $context->client->organization ?? throw new AuthorizationException;
        }

        $user = $request->user();
        $organization = $user instanceof User ? $user->membership?->organization : null;

        return $organization ?? throw new AuthorizationException;
    }

    /**
     * Public API tenancy: an object of another organization is 404 (§8.6).
     */
    protected function ensureOwnedByClient(Request $request, int $organizationId): void
    {
        if (ApiClientContext::from($request)->organizationId() !== $organizationId) {
            throw new ModelNotFoundException;
        }
    }

    protected function perPage(Request $request, int $default, int $max): int
    {
        $perPage = $request->query('per_page');

        return is_numeric($perPage) ? max(1, min($max, (int) $perPage)) : $default;
    }

    /**
     * `updated_since` (RFC 3339, API.md §3.0), or null when absent. 422 when malformed.
     */
    protected function updatedSince(Request $request): ?CarbonImmutable
    {
        $value = $request->query('updated_since');

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_string($value) ? Date::parse($value)->utc() : throw new InvalidArgumentException;
        } catch (Throwable) {
            $message = __('validation.date', ['attribute' => 'updated_since']);

            throw ValidationException::withMessages(['updated_since' => [is_string($message) ? $message : 'updated_since']]);
        }
    }
}
