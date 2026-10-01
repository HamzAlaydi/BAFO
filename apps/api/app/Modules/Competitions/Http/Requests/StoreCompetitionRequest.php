<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

/**
 * `POST /competitions` (API.md §1.4, §3.4): a draft. Title, category, region, direction and
 * format are required.
 */
final class StoreCompetitionRequest extends CompetitionRequest
{
    protected function creating(): bool
    {
        return true;
    }
}
