<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\PublicV1;

use App\Modules\Bidding\Actions\RecordAwardErpSync;
use App\Modules\Bidding\Http\Requests\ListPublicAwardsRequest;
use App\Modules\Bidding\Http\Requests\RecordErpSyncRequest;
use App\Modules\Bidding\Http\Resources\PublicAwardResource;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Support\UpdatedAtCursor;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Integrations\Models\ExternalRef;
use Illuminate\Http\JsonResponse;

/**
 * Awards for the issuer's ERP (API.md §3.5, flow F6).
 *
 *   GET  /awards                     awards:read   cursor list, the ERP polling endpoint
 *   GET  /awards/{award}             awards:read   PublicAward
 *   POST /awards/{award}/erp-sync    awards:sync   the ERP write-back (Idempotency-Key)
 */
final class PublicAwardController extends PublicController
{
    public function index(ListPublicAwardsRequest $request): JsonResponse
    {
        $organizationId = $this->client()->organizationId;
        $competitions = Competition::query()->select('id')->where('organization_id', $organizationId);

        $query = Award::query()
            ->whereIn('competition_id', $competitions)
            ->with(PublicAwardResource::relations());

        if (($status = $request->filter('status')) !== null) {
            $query->where('status', $status);
        }

        if (($syncStatus = $request->filter('erp_sync_status')) !== null) {
            $query->where('erp_sync_status', $syncStatus);
        }

        if (($competitionId = $request->filter('competition_id')) !== null) {
            $query->whereIn('competition_id', Competition::query()
                ->select('id')
                ->where('organization_id', $organizationId)
                ->where('public_id', strtolower($competitionId)));
        }

        if (($since = $request->updatedSince()) !== null) {
            $query->where('updated_at', '>=', $since);
        }

        $system = $request->filter('external_system');
        $externalId = $request->filter('external_id');

        if ($system !== null && $externalId !== null) {
            $query->whereIn('competition_id', ExternalRef::query()
                ->select('refable_id')
                ->where('organization_id', $organizationId)
                ->where('refable_type', (new Competition)->getMorphClass())
                ->where('system', $system)
                ->where('value', $externalId));
        }

        $page = UpdatedAtCursor::paginate($query, $request->perPage(), $request->filter('cursor'));

        return $this->ok(PublicAwardResource::collection($page['items']), ['pagination' => $page['pagination']]);
    }

    public function show(string $award): JsonResponse
    {
        return $this->ok(new PublicAwardResource($this->award($award)->load(PublicAwardResource::relations())));
    }

    public function erpSync(RecordErpSyncRequest $request, string $award, RecordAwardErpSync $sync): JsonResponse
    {
        $model = $sync->handle(
            $this->award($award),
            $request->syncStatus(),
            $request->message(),
            $request->externalRefs(),
            $this->client(),
        );

        return $this->ok(new PublicAwardResource($model->load(PublicAwardResource::relations())));
    }
}
