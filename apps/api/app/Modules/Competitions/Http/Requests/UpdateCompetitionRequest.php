<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

/**
 * `PATCH /competitions/{competition}` (API.md §1.4, §3.4): partial; which fields are allowed
 * depends on the status (UpdateCompetition).
 */
final class UpdateCompetitionRequest extends CompetitionRequest
{
    protected function creating(): bool
    {
        return false;
    }
}
