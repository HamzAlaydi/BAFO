<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Controllers\AppV1;

use App\Modules\Bidding\Actions\RequestCompetitionReport;
use App\Modules\Bidding\Http\Controllers\BiddingController;
use App\Modules\Bidding\Http\Requests\ShowReportRequest;
use App\Modules\Bidding\Http\Resources\ReportResource;
use App\Modules\Bidding\Services\LiveStateManager;
use App\Modules\Competitions\Models\Competition;
use Illuminate\Http\JsonResponse;

/**
 * GET /competitions/{competition}/report?locale= (API.md §1.6): issuer, any member. 200 with the
 * current report, or 202 while it is generated (poll every 3 s). The PDF downloads through
 * `GET /files/{file}/download`.
 */
final class ReportController extends BiddingController
{
    public function show(ShowReportRequest $request, Competition $competition, RequestCompetitionReport $reports, LiveStateManager $liveStates): JsonResponse
    {
        $this->issuerViewer($competition);

        $report = $reports->handle($competition, $request->reportLocale());
        $current = RequestCompetitionReport::isCurrent($report, $liveStates->read($competition)->version);

        return $this->ok(new ReportResource($report->load('file')), [], $current ? 200 : 202);
    }
}
