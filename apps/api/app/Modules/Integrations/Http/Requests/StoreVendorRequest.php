<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

/**
 * `POST /vendors` (app, API.md §1.9; public, §3.3).
 */
final class StoreVendorRequest extends VendorRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->vendorRules(partial: false);
    }
}
