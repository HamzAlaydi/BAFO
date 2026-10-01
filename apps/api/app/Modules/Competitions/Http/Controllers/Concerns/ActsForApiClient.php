<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\Concerns;

use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Enums\ViewerRole;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Modules\Identity\Models\Organization;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use App\Support\Exceptions\ApiException;

/**
 * Public API tenancy (ARCHITECTURE §8.6): the organization comes from the API client, and a
 * competition is visible only when that organization is its issuer; anything else is 404.
 * Policies are not used for API clients; the Actions enforce the state rules.
 */
trait ActsForApiClient
{
    protected function actor(): Actor
    {
        return CurrentActor::get();
    }

    protected function organization(): Organization
    {
        return Organization::query()->findOrFail($this->organizationId());
    }

    protected function organizationId(): int
    {
        return $this->actor()->organizationId ?? throw ViewerResolver::notFound();
    }

    /**
     * @throws ApiException not_found (404) for another organization's competition
     */
    protected function owned(Competition $competition): Viewer
    {
        if ($competition->organization_id !== $this->organizationId()) {
            throw ViewerResolver::notFound();
        }

        return new Viewer(ViewerRole::ApiClient, $competition->organization_id);
    }
}
