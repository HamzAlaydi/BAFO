<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /competitions/{competition}/extend` (API.md §1.4, ARCHITECTURE §7.17).
 */
final class ExtendCompetitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'new_close_at' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'new_close_at' => self::trans('competitions.attributes.new_close_at'),
            'reason' => self::trans('competitions.attributes.reason'),
        ];
    }

    public function newCloseAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('new_close_at')->toString())->utc();
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }

    private static function trans(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}
