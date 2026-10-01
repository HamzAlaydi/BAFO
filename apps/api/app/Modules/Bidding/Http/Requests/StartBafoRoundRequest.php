<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

use App\Support\Settings\Settings;

/**
 * POST /competitions/{competition}/bafo-round (API.md §1.6): 1–50 participant ids and an
 * optional duration within the `bidding.bafo_duration_bounds` setting.
 */
final class StartBafoRoundRequest extends BiddingRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $bounds = app(Settings::class)->get('bidding.bafo_duration_bounds', ['min' => 15, 'max' => 4320]);
        $min = is_array($bounds) && is_numeric($bounds['min'] ?? null) ? (int) $bounds['min'] : 15;
        $max = is_array($bounds) && is_numeric($bounds['max'] ?? null) ? (int) $bounds['max'] : 4320;

        return [
            'participant_ids' => ['required', 'array', 'min:1', 'max:50'],
            'participant_ids.*' => ['required', 'string', 'max:26', 'distinct:ignore_case'],
            'duration_minutes' => ['nullable', 'integer', "min:{$min}", "max:{$max}"],
        ];
    }

    /**
     * @return list<string>
     */
    public function participantIds(): array
    {
        /** @var list<string> $ids */
        $ids = array_values((array) $this->validated('participant_ids'));

        return $ids;
    }

    public function durationMinutes(): ?int
    {
        $value = $this->validated('duration_minutes');

        return is_numeric($value) ? (int) $value : null;
    }
}
