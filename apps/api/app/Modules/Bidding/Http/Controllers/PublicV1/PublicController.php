<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\PublicV1;

use App\Modules\Bidding\Models\Award;
use App\Modules\Competitions\Models\Competition;
use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use App\Support\Exceptions\ApiException;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;

/**
 * Shared helpers of the Bidding public v1 controllers (ARCHITECTURE §8.6): the organization
 * comes from the API client, and every lookup is scoped to it. A competition is visible only
 * when that organization is its issuer; anything else is 404.
 */
abstract class PublicController extends ApiController
{
    protected function client(): Actor
    {
        $actor = CurrentActor::get();

        if (! $actor->isApiClient() || $actor->organizationId === null) {
            throw new AuthenticationException;
        }

        return $actor;
    }

    protected function competition(string $id): Competition
    {
        $competition = Str::isUlid($id)
            ? Competition::query()
                ->where('public_id', strtolower($id))
                ->where('organization_id', $this->client()->organizationId)
                ->first()
            : null;

        return $competition ?? throw new ApiException('not_found', status: 404);
    }

    protected function award(string $id): Award
    {
        $organizationId = $this->client()->organizationId;

        $award = Str::isUlid($id)
            ? Award::query()
                ->where('public_id', strtolower($id))
                ->whereIn('competition_id', Competition::query()->select('id')->where('organization_id', $organizationId))
                ->first()
            : null;

        return $award ?? throw new ApiException('not_found', status: 404);
    }
}
