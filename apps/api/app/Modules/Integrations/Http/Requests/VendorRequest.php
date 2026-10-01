<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Requests;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Integrations\Models\ExternalRef;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Vendor input shared by the app (`region_id`, `category_ids[]` as public ids) and the public API
 * (`region_code`, `category_codes[]`), API.md §1.9 and §3.3. `vendorData()` returns the
 * normalised attributes with internal ids for the vendor Actions.
 */
abstract class VendorRequest extends FormRequest
{
    public const string PHONE_PATTERN = '/^\+9665\d{8}$/';

    public const string CR_PATTERN = '/^\d{10}$/';

    public const string VAT_PATTERN = '/^3\d{13}3$/';

    public const string REF_TYPE_PATTERN = '/^[a-z0-9_]+$/';

    public function authorize(): bool
    {
        return true;
    }

    public function isPublicApi(): bool
    {
        return str_starts_with((string) $this->route()?->getName(), 'public.v1.');
    }

    /**
     * @return array<string, mixed>
     */
    public function vendorData(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $data = array_intersect_key($validated, array_flip([
            'name', 'name_en', 'cr_number', 'vat_number', 'email', 'contact_name', 'phone', 'city', 'status', 'notes',
        ]));

        foreach (['name', 'name_en', 'contact_name', 'city', 'notes'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]) === '' ? null : trim($data[$field]);
            }
        }

        if (isset($data['email']) && is_string($data['email'])) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        $regionKey = $this->isPublicApi() ? 'region_code' : 'region_id';

        if (array_key_exists($regionKey, $validated)) {
            $region = $validated[$regionKey];
            $data['region_id'] = is_string($region)
                ? Region::query()->where($this->isPublicApi() ? 'code' : 'public_id', $region)->value('id')
                : null;
        }

        $categoriesKey = $this->isPublicApi() ? 'category_codes' : 'category_ids';

        if (array_key_exists($categoriesKey, $validated)) {
            /** @var list<string> $categories */
            $categories = $validated[$categoriesKey] ?? [];
            $data['category_ids'] = Category::query()
                ->whereIn($this->isPublicApi() ? 'code' : 'public_id', $categories)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        if (array_key_exists('external_refs', $validated) && is_array($validated['external_refs'])) {
            $data['external_refs'] = array_values(array_map(static fn (array $ref): array => [
                'system' => (string) $ref['system'],
                'type' => (string) $ref['type'],
                'id' => trim((string) $ref['id']),
                'number' => isset($ref['number']) ? (string) $ref['number'] : null,
                'url' => isset($ref['url']) ? (string) $ref['url'] : null,
            ], $validated['external_refs']));
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => self::trans('integrations.validation.phone'),
            'cr_number.regex' => self::trans('integrations.validation.cr_number'),
            'vat_number.regex' => self::trans('integrations.validation.vat_number'),
            'external_refs.*.system.regex' => self::trans('integrations.validation.external_system'),
            'external_refs.*.type.regex' => self::trans('integrations.validation.external_type'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['name', 'name_en', 'email', 'contact_name', 'phone', 'cr_number', 'vat_number', 'region_id', 'region_code',
            'city', 'category_ids', 'category_codes', 'status', 'notes', 'external_refs', 'system', 'external_id'] as $field) {
            $attributes[$field] = self::trans('integrations.attributes.'.$field);
        }

        return $attributes;
    }

    /**
     * @param  bool  $partial  PATCH: every field is `sometimes`
     * @return array<string, list<mixed>>
     */
    protected function vendorRules(bool $partial, bool $withExternalRefs = true): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];
        $optional = $partial ? ['sometimes', 'nullable'] : ['nullable'];

        $rules = [
            'name' => [...$required, 'string', 'max:200'],
            'name_en' => [...$optional, 'string', 'max:200'],
            'email' => [...$required, 'string', 'email', 'max:255'],
            'contact_name' => [...$optional, 'string', 'max:150'],
            'phone' => [...$optional, 'string', 'regex:'.self::PHONE_PATTERN],
            'cr_number' => [...$optional, 'string', 'regex:'.self::CR_PATTERN],
            'vat_number' => [...$optional, 'string', 'regex:'.self::VAT_PATTERN],
            'city' => [...$optional, 'string', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'blocked'])],
            'notes' => [...$optional, 'string', 'max:5000'],
        ];

        if ($this->isPublicApi()) {
            $rules['region_code'] = [...$optional, 'string', Rule::exists('regions', 'code')->where('is_active', true)];
            $rules['category_codes'] = ['sometimes', 'array', 'max:50'];
            $rules['category_codes.*'] = ['string', 'distinct', Rule::exists('categories', 'code')->where('is_active', true)];
        } else {
            $rules['region_id'] = [...$optional, 'string', Rule::exists('regions', 'public_id')->where('is_active', true)];
            $rules['category_ids'] = ['sometimes', 'array', 'max:50'];
            $rules['category_ids.*'] = ['string', 'distinct', Rule::exists('categories', 'public_id')->where('is_active', true)];
        }

        if ($withExternalRefs) {
            $rules['external_refs'] = ['sometimes', 'array', 'max:20'];
            $rules['external_refs.*'] = ['array'];
            $rules['external_refs.*.system'] = ['required', 'string', 'max:60', 'regex:'.ExternalRef::SYSTEM_PATTERN];
            $rules['external_refs.*.type'] = ['required', 'string', 'max:60', 'regex:'.self::REF_TYPE_PATTERN];
            $rules['external_refs.*.id'] = ['required', 'string', 'max:120'];
            $rules['external_refs.*.number'] = ['nullable', 'string', 'max:120'];
            $rules['external_refs.*.url'] = ['nullable', 'string', 'url:https,http', 'max:500'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        // Input ids are accepted in any case (API.md §0.6).
        $region = $this->input('region_id');
        $categories = $this->input('category_ids');

        $this->merge(array_filter([
            'region_id' => is_string($region) && Str::isUlid($region) ? strtolower($region) : null,
            'category_ids' => is_array($categories)
                ? array_map(static fn (mixed $id): mixed => is_string($id) ? strtolower($id) : $id, $categories)
                : null,
        ], static fn (mixed $value): bool => $value !== null));
    }

    protected static function trans(string $key): string
    {
        $value = __($key);

        return is_string($value) ? $value : $key;
    }
}
