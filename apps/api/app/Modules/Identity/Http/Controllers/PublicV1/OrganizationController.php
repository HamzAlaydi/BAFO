<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\PublicV1;

use App\Modules\Identity\Http\Resources\PublicOrganizationResource;
use App\Modules\Identity\Models\Organization;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/public/v1/organization` (scope `organization:read`, API.md §3.2): the API client's
 * own organization, which Integrations' `api.client` middleware puts on the current actor.
 */
final class OrganizationController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $actor = CurrentActor::get();

        if (! $actor->isApiClient() || $actor->organizationId === null) {
            throw new AuthenticationException;
        }

        $organization = Organization::query()->findOrFail($actor->organizationId);

        return $this->ok(new PublicOrganizationResource($organization));
    }
}
