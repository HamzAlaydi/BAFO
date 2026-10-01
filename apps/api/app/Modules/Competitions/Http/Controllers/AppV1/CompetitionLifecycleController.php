<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Actions\CancelCompetition;
use App\Modules\Competitions\Actions\CloseWithoutAward;
use App\Modules\Competitions\Actions\ExtendCompetition;
use App\Modules\Competitions\Actions\PublishCompetition;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForCaller;
use App\Modules\Competitions\Http\Requests\CancelCompetitionRequest;
use App\Modules\Competitions\Http\Requests\CloseCompetitionRequest;
use App\Modules\Competitions\Http\Requests\ExtendCompetitionRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionPresenter;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lifecycle actions of the issuer (API.md §1.4): publish, extend, cancel, close without award.
 * Each returns the Competition (issuer projection).
 */
final class CompetitionLifecycleController extends ApiController
{
    use ActsForCaller;

    public function __construct(
        private readonly ViewerResolver $viewers,
        private readonly CompetitionPresenter $presenter,
    ) {}

    public function publish(Request $request, Competition $competition, PublishCompetition $publish): JsonResponse
    {
        $this->authorize('manage', $competition);

        return $this->respond($request, $publish->handle($competition, $this->actor()));
    }

    public function extend(ExtendCompetitionRequest $request, Competition $competition, ExtendCompetition $extend): JsonResponse
    {
        $this->authorize('manage', $competition);

        return $this->respond($request, $extend->handle($competition, $request->newCloseAt(), $request->reason(), $this->actor()));
    }

    public function cancel(CancelCompetitionRequest $request, Competition $competition, CancelCompetition $cancel): JsonResponse
    {
        $this->authorize('manage', $competition);

        return $this->respond($request, $cancel->handle($competition, $request->reason(), $request->note(), $this->actor()));
    }

    public function close(CloseCompetitionRequest $request, Competition $competition, CloseWithoutAward $close): JsonResponse
    {
        $this->authorize('award', $competition);

        return $this->respond($request, $close->handle($competition, $request->reason(), $request->note(), $this->actor()));
    }

    private function respond(Request $request, Competition $competition): JsonResponse
    {
        $competition = $competition->fresh() ?? $competition;

        return $this->ok($this->presenter->present($competition, $this->viewers->for($competition, $this->actor()), $this->user($request)));
    }
}
