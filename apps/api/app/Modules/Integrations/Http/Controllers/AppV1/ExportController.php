<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\AppV1;

use App\Modules\Integrations\Actions\Exports\StartExport;
use App\Modules\Integrations\Http\Concerns\ResolvesTenant;
use App\Modules\Integrations\Http\Requests\StoreExportRequest;
use App\Modules\Integrations\Http\Resources\ExportJobResource;
use App\Modules\Integrations\Models\ExportJob;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Exports in the dashboard (API.md §1.9, ARCHITECTURE §14.8): results, offer log, awards and
 * vendors, as CSV or XLSX. The route group applies `integrations.access`.
 */
final class ExportController extends ApiController
{
    use ResolvesTenant;

    public function store(StoreExportRequest $request, StartExport $start): JsonResponse
    {
        $job = $start->handle(
            $this->organization($request),
            $request->exportType(),
            $request->exportFormat(),
            $request->filters(),
            CurrentActor::get(),
        );

        return $this->ok(new ExportJobResource($job->refresh()), status: 202);
    }

    public function show(ExportJob $job): JsonResponse
    {
        $this->authorize('view', $job);

        return $this->ok(new ExportJobResource($job));
    }
}
