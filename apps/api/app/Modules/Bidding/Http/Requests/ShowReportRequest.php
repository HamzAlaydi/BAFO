<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Requests;

use Illuminate\Support\Facades\App;

/**
 * GET /competitions/{competition}/report (API.md §1.6): `locale` in ar, en (default: the request
 * locale).
 */
final class ShowReportRequest extends BiddingRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['nullable', 'string', 'in:ar,en'],
        ];
    }

    public function reportLocale(): string
    {
        $locale = $this->validated('locale');

        return is_string($locale) ? $locale : (App::getLocale() === 'en' ? 'en' : 'ar');
    }
}
