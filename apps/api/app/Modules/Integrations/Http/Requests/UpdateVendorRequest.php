<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

/**
 * `PATCH /vendors/{vendor}` (app and public): any create field; `external_refs` replaces the
 * whole list.
 */
final class UpdateVendorRequest extends VendorRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->vendorRules(partial: true);
    }
}
