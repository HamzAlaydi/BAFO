<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Http\Requests\SuggestionsRequest;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Queries\SupplierSuggestions;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * `GET /competitions/{competition}/suggestions` (API.md §1.4): suppliers to invite.
 */
final class CompetitionSuggestionController extends ApiController
{
    public function __invoke(SuggestionsRequest $request, Competition $competition, SupplierSuggestions $suggestions): JsonResponse
    {
        $this->authorize('manage', $competition);

        $categoryId = $request->filled('category_id') ? SupplierSuggestions::categoryId($request->string('category_id')->toString()) : null;
        $regionId = $request->filled('region_id') ? SupplierSuggestions::regionId($request->string('region_id')->toString()) : null;

        return $this->ok($suggestions->find(
            $competition,
            $request->filled('q') ? $request->string('q')->toString() : null,
            $categoryId,
            $regionId,
            (int) ($request->integer('limit') ?: 20),
        ));
    }
}
