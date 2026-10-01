<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `GET /notifications` query (API.md §1.8, §0.5): `unread`, `page`, `per_page` (default 20, max 100).
 */
final class ListNotificationsRequest extends FormRequest
{
    public const int DEFAULT_PER_PAGE = 20;

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
            'unread' => ['sometimes', 'nullable', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unread' => $this->attribute('unread'),
            'page' => $this->attribute('page'),
            'per_page' => $this->attribute('per_page'),
        ];
    }

    public function unreadOnly(): bool
    {
        return $this->boolean('unread');
    }

    public function perPage(): int
    {
        return $this->integer('per_page', self::DEFAULT_PER_PAGE);
    }

    private function attribute(string $field): string
    {
        $label = __('notifications.attributes.'.$field);

        return is_string($label) ? $label : $field;
    }
}
