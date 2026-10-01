<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A close reason and a note (API.md §1.4 cancel and close without award; §3.4 with
 * `close_reason_code`). The reason must be active and of the endpoint's kind; the note is
 * required when the reason `requires_note` (checked by the Action).
 */
abstract class CloseReasonRequest extends FormRequest
{
    abstract protected function kind(): CloseReasonKind;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('close_reason_code');
        $id = $this->input('close_reason_id');

        if (is_string($id)) {
            $this->merge(['close_reason_id' => strtolower(trim($id))]);
        } elseif (is_string($code)) {
            $this->merge([
                'close_reason_id' => CloseReason::query()->where('code', $code)->value('public_id') ?? '',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'close_reason_id' => ['required', 'string', Rule::exists('close_reasons', 'public_id')
                ->where('is_active', true)
                ->where('kind', $this->kind()->value)],
            'close_reason_code' => ['sometimes', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'close_reason_id' => $this->trans('competitions.attributes.close_reason_id'),
            'close_reason_code' => $this->trans('competitions.attributes.close_reason_id'),
            'note' => $this->trans('competitions.attributes.note'),
        ];
    }

    public function reason(): CloseReason
    {
        return CloseReason::query()->where('public_id', $this->string('close_reason_id')->toString())->firstOrFail();
    }

    public function note(): ?string
    {
        $note = $this->input('note');

        return is_string($note) ? $note : null;
    }

    private function trans(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}
