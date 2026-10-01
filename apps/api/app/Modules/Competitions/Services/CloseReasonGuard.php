<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use Illuminate\Validation\ValidationException;

/**
 * The reason rules of cancel and close without award (API.md §1.4): an active reason of the
 * expected kind, and a note when the reason `requires_note` (the "Other" reasons). Checked by
 * the Actions too, because the admin panel calls them without a FormRequest.
 */
final class CloseReasonGuard
{
    /**
     * @throws ValidationException
     */
    public static function assert(CloseReason $reason, CloseReasonKind $kind, ?string $note, string $field = 'close_reason_id'): void
    {
        if ($reason->kind !== $kind || ! $reason->is_active) {
            throw ValidationException::withMessages([$field => [self::message('close_reason_kind')]]);
        }

        if ($reason->requires_note && self::cleanNote($note) === null) {
            throw ValidationException::withMessages(['note' => [self::message('note_required')]]);
        }
    }

    public static function cleanNote(?string $note): ?string
    {
        $note = $note !== null ? trim($note) : null;

        return $note === '' ? null : $note;
    }

    private static function message(string $rule): string
    {
        $message = __('competitions.validation.'.$rule);

        return is_string($message) ? $message : $rule;
    }
}
