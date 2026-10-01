<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use App\Modules\Competitions\Enums\CompetitionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `GET /competitions` query (API.md §1.4).
 */
final class ListCompetitionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['issuer', 'participant'])],
            'status' => ['nullable', 'string', 'max:200'],
            'status_group' => ['nullable', Rule::in(['active', 'draft', 'ended', 'all'])],
            'direction' => ['nullable', Rule::in(['tender', 'auction'])],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['-updated_at', 'effective_close_at', '-created_at'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->statuses() as $status) {
                    if (CompetitionStatus::tryFrom($status) === null) {
                        $message = __('validation.in', ['attribute' => 'status']);
                        $validator->errors()->add('status', is_string($message) ? $message : 'status');

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        $status = $this->input('status');

        if (! is_string($status) || trim($status) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $status)), static fn (string $s): bool => $s !== ''));
    }

    /**
     * @return list<string>
     */
    public function statusGroup(): array
    {
        return match ($this->input('status_group')) {
            'active' => ['scheduled', 'live', 'closed', 'bafo_round'],
            'draft' => ['draft'],
            'ended' => ['awarded', 'not_awarded', 'cancelled'],
            default => [],
        };
    }

    public function perPage(): int
    {
        return (int) ($this->integer('per_page') ?: 20);
    }
}
