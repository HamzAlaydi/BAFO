<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\ExportType;
use Illuminate\Validation\Rule;

/**
 * `POST /integrations/exports` (API.md §1.9): `type`, `format`, `competition_id` (required for
 * results and offer_log; a competition the caller's organization issued), `from` / `to` (dates,
 * awards only).
 */
final class StoreExportRequest extends IntegrationsRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $organizationId = $this->user()?->membership?->organization_id;

        return [
            'type' => ['required', 'string', Rule::enum(ExportType::class)],
            'format' => ['required', 'string', Rule::enum(ExportFormat::class)],
            'competition_id' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('type'), [ExportType::Results->value, ExportType::OfferLog->value], true)),
                Rule::prohibitedIf(fn (): bool => ! in_array($this->input('type'), [ExportType::Results->value, ExportType::OfferLog->value], true)),
                'string',
                // Existence is not revealed: another organization's competition is "invalid" like an unknown id.
                Rule::exists('competitions', 'public_id')->where('organization_id', $organizationId)->whereNull('deleted_at'),
            ],
            'from' => ['nullable', Rule::prohibitedIf(fn (): bool => $this->input('type') !== ExportType::Awards->value), 'date_format:Y-m-d'],
            'to' => ['nullable', Rule::prohibitedIf(fn (): bool => $this->input('type') !== ExportType::Awards->value), 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function exportType(): ExportType
    {
        return ExportType::from((string) $this->validated('type'));
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::from((string) $this->validated('format'));
    }

    /**
     * The stored `filters` (ARCHITECTURE §5.4): `{competition_id}` (public id) or `{from, to}`.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter([
            'competition_id' => $this->nullableString('competition_id'),
            'from' => $this->nullableString('from'),
            'to' => $this->nullableString('to'),
        ], static fn (?string $value): bool => $value !== null);
    }

    protected function prepareForValidation(): void
    {
        $competitionId = $this->input('competition_id');

        if (is_string($competitionId)) {
            $this->merge(['competition_id' => strtolower($competitionId)]);
        }
    }
}
