<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Catalog\Enums\CloseReasonKind;

/**
 * `POST /competitions/{competition}/cancel`: a reason of kind `cancel`.
 */
final class CancelCompetitionRequest extends CloseReasonRequest
{
    protected function kind(): CloseReasonKind
    {
        return CloseReasonKind::Cancel;
    }
}
