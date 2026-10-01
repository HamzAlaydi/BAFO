<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Catalog\Enums\CloseReasonKind;

/**
 * `POST /competitions/{competition}/close` (close without award): a reason of kind `not_awarded`.
 */
final class CloseCompetitionRequest extends CloseReasonRequest
{
    protected function kind(): CloseReasonKind
    {
        return CloseReasonKind::NotAwarded;
    }
}
