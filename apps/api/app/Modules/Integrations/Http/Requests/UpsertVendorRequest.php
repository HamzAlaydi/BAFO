<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Integrations\Models\ExternalRef;

/**
 * Public `PUT /vendors/external/{system}/{external_id}` (API.md §3.3): the create body without
 * `external_refs`; the path supplies the ERP key, validated here as `system` and `external_id`.
 */
final class UpsertVendorRequest extends VendorRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->vendorRules(partial: false, withExternalRefs: false),
            'system' => ['required', 'string', 'max:60', 'regex:'.ExternalRef::SYSTEM_PATTERN],
            'external_id' => ['required', 'string', 'max:120'],
        ];
    }

    public function system(): string
    {
        return (string) $this->route('system');
    }

    public function externalId(): string
    {
        return trim((string) $this->route('external_id'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'system.regex' => self::trans('integrations.validation.external_system')];
    }

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [...$this->all(), 'system' => $this->system(), 'external_id' => $this->externalId()];
    }
}
