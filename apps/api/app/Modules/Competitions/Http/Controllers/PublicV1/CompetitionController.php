<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\PublicV1;

use App\Modules\Competitions\Actions\CancelCompetition;
use App\Modules\Competitions\Actions\CloseWithoutAward;
use App\Modules\Competitions\Actions\CreateCompetitionFromApi;
use App\Modules\Competitions\Actions\DeleteDraftCompetition;
use App\Modules\Competitions\Actions\ExtendCompetition;
use App\Modules\Competitions\Actions\PublishCompetition;
use App\Modules\Competitions\Actions\UpdateCompetition;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForApiClient;
use App\Modules\Competitions\Http\Requests\CancelCompetitionRequest;
use App\Modules\Competitions\Http\Requests\CloseCompetitionRequest;
use App\Modules\Competitions\Http\Requests\ExtendCompetitionRequest;
use App\Modules\Competitions\Http\Requests\StorePublicCompetitionRequest;
use App\Modules\Competitions\Http\Requests\UpdateCompetitionRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Queries\KeysetCursor;
use App\Modules\Competitions\Services\CompetitionPresenter;
use App\Support\Http\ApiResponse;
use App\Support\Http\Controllers\ApiController;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API competitions (API.md §3.4, flows F2 and F3): the issuer projection as
 * PublicCompetition, scoped to the API client's organization.
 */
final class CompetitionController extends ApiController
{
    use ActsForApiClient;

    public function __construct(private readonly CompetitionPresenter $presenter) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:200'],
            'direction' => ['nullable', 'in:tender,auction'],
            'format' => ['nullable', 'in:live,sealed'],
            'updated_since' => ['nullable', 'date'],
            'external_system' => ['nullable', 'string', 'max:60', 'required_with:external_id'],
            'external_id' => ['nullable', 'string', 'max:120', 'required_with:external_system'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'string'],
        ]);

        $statuses = array_values(array_filter(array_map('trim', explode(',', (string) ($validated['status'] ?? '')))));

        $query = Competition::query()
            ->where('organization_id', $this->organizationId())
            ->with(['category', 'region', 'organization.logoFile', 'cancelReason', 'notAwardedReason', 'createdBy', 'createdByApiClient', 'externalRefs'])
            ->when($statuses !== [], static fn (Builder $q) => $q->whereIn('status', $statuses))
            ->when(isset($validated['direction']), static fn (Builder $q) => $q->where('direction', $validated['direction']))
            ->when(isset($validated['format']), static fn (Builder $q) => $q->where('format', $validated['format']))
            ->when(isset($validated['updated_since']), static fn (Builder $q) => $q->where('updated_at', '>=', CarbonImmutable::parse((string) $validated['updated_since'])->utc()))
            ->when(isset($validated['external_system']), static fn (Builder $q) => $q->whereHas('externalRefs', static fn (Builder $r) => $r
                ->where('system', $validated['external_system'])
                ->where('value', $validated['external_id'])));

        $page = KeysetCursor::paginate($query, (int) ($validated['per_page'] ?? 50), isset($validated['cursor']) ? (string) $validated['cursor'] : null);

        $data = $page['items']
            ->map(fn (Competition $competition): array => $this->presenter->publicCompetition(CompetitionPresenter::load($competition), $this->owned($competition)))
            ->values()->all();

        return ApiResponse::ok($data, ['pagination' => $page['pagination']]);
    }

    public function store(StorePublicCompetitionRequest $request, CreateCompetitionFromApi $create): JsonResponse
    {
        $competition = $create->handle(
            $this->organization(),
            $request->toInput(),
            $request->rows($this->organizationId()),
            $request->hasSponsorship() ? ['mode' => $request->sponsorshipMode(), 'max_passes' => $request->maxPasses()] : null,
            $this->actor(),
        );

        return $this->created($this->present($competition))
            ->header('Location', route('public.v1.competitions.show', $competition->public_id));
    }

    public function show(Competition $competition): JsonResponse
    {
        return $this->ok($this->present($competition));
    }

    public function update(UpdateCompetitionRequest $request, Competition $competition, UpdateCompetition $update): JsonResponse
    {
        $this->owned($competition);

        return $this->ok($this->present($update->handle($competition, $request->toInput(), $this->actor())));
    }

    public function destroy(Competition $competition, DeleteDraftCompetition $delete): JsonResponse
    {
        $this->owned($competition);

        $delete->handle($competition, $this->actor());

        return $this->noContent();
    }

    public function publish(Competition $competition, PublishCompetition $publish): JsonResponse
    {
        $this->owned($competition);

        return $this->ok($this->present($publish->handle($competition, $this->actor())));
    }

    public function extend(ExtendCompetitionRequest $request, Competition $competition, ExtendCompetition $extend): JsonResponse
    {
        $this->owned($competition);

        return $this->ok($this->present($extend->handle($competition, $request->newCloseAt(), $request->reason(), $this->actor())));
    }

    public function cancel(CancelCompetitionRequest $request, Competition $competition, CancelCompetition $cancel): JsonResponse
    {
        $this->owned($competition);

        return $this->ok($this->present($cancel->handle($competition, $request->reason(), $request->note(), $this->actor())));
    }

    public function close(CloseCompetitionRequest $request, Competition $competition, CloseWithoutAward $close): JsonResponse
    {
        $this->owned($competition);

        return $this->ok($this->present($close->handle($competition, $request->reason(), $request->note(), $this->actor())));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Competition $competition): array
    {
        $viewer = $this->owned($competition);
        $fresh = $competition->fresh() ?? $competition;

        return $this->presenter->publicCompetition(CompetitionPresenter::load($fresh), $viewer);
    }
}
