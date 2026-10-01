<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Data\OrganizationProfileData;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * The organization profile fields of `POST /auth/register` (under `organization.`) and
 * `PATCH /organization` (top level), API.md §1.3, and their mapping to `organizations` columns.
 */
final class OrganizationProfileFields
{
    public const string CR_PATTERN = '/^\d{10}$/';

    public const string VAT_PATTERN = '/^3\d{13}3$/';

    /**
     * National address input key => column.
     *
     * @var array<string, string>
     */
    public const array ADDRESS_COLUMNS = [
        'building_number' => 'address_building_number',
        'street' => 'address_street',
        'district' => 'address_district',
        'postal_code' => 'address_postal_code',
        'additional_number' => 'address_additional_number',
        'short_address' => 'address_short',
    ];

    /**
     * Plain input key => column (the same name).
     *
     * @var list<string>
     */
    public const array PLAIN_COLUMNS = [
        'name',
        'city',
        'vat_registered',
        'vat_number',
        'legal_name_ar',
        'legal_name_en',
        'website',
        'visible_in_suggestions',
    ];

    /**
     * @param  string  $prefix  `organization.` on register, `` on PATCH
     * @param  bool  $partial  PATCH: every field is `sometimes`
     * @return array<string, list<mixed>>
     */
    public static function rules(string $prefix, bool $partial): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            $prefix.'name' => [...$required, 'string', 'max:150'],
            $prefix.'region_id' => [...$required, 'string', Rule::exists(Region::class, 'public_id')->where('is_active', true)],
            $prefix.'city' => [...$required, 'string', 'max:100'],
            $prefix.'vat_registered' => ['sometimes', 'boolean'],
            $prefix.'vat_number' => [
                'nullable',
                'string',
                'regex:'.self::VAT_PATTERN,
                ...($partial ? [] : ['required_if_accepted:'.$prefix.'vat_registered']),
            ],
            $prefix.'legal_name_ar' => ['sometimes', 'nullable', 'string', 'max:200'],
            $prefix.'legal_name_en' => ['sometimes', 'nullable', 'string', 'max:200'],
            $prefix.'website' => ['sometimes', 'nullable', 'string', 'max:255', 'url:https'],
            $prefix.'national_address' => ['sometimes', 'nullable', 'array'],
            $prefix.'national_address.building_number' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}$/'],
            $prefix.'national_address.street' => ['sometimes', 'nullable', 'string', 'max:150'],
            $prefix.'national_address.district' => ['sometimes', 'nullable', 'string', 'max:150'],
            $prefix.'national_address.postal_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{5}$/'],
            $prefix.'national_address.additional_number' => ['sometimes', 'nullable', 'string', 'regex:/^\d{4}$/'],
            $prefix.'national_address.short_address' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{4}\d{4}$/'],
            $prefix.'category_ids' => ['sometimes', 'nullable', 'array', 'max:20'],
            $prefix.'category_ids.*' => ['string', 'distinct', Rule::exists(Category::class, 'public_id')->where('is_active', true)],
            $prefix.'visible_in_suggestions' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix): array
    {
        return [
            $prefix.'vat_number.regex' => self::text('identity.validation.vat_number'),
            $prefix.'vat_number.required_if_accepted' => self::text('identity.validation.vat_number_required'),
            $prefix.'website.url' => self::text('identity.validation.website'),
            $prefix.'national_address.building_number.regex' => self::text('identity.validation.four_digits'),
            $prefix.'national_address.postal_code.regex' => self::text('identity.validation.postal_code'),
            $prefix.'national_address.additional_number.regex' => self::text('identity.validation.four_digits'),
            $prefix.'national_address.short_address.regex' => self::text('identity.validation.short_address'),
        ];
    }

    /**
     * Field path => key under `identity.attributes`.
     *
     * @return array<string, string>
     */
    public static function attributeKeys(string $prefix): array
    {
        $keys = [
            $prefix.'name' => 'organization.name',
            $prefix.'cr_number' => 'organization.cr_number',
            $prefix.'region_id' => 'organization.region_id',
            $prefix.'city' => 'organization.city',
            $prefix.'vat_registered' => 'organization.vat_registered',
            $prefix.'vat_number' => 'organization.vat_number',
            $prefix.'legal_name_ar' => 'organization.legal_name_ar',
            $prefix.'legal_name_en' => 'organization.legal_name_en',
            $prefix.'website' => 'organization.website',
            $prefix.'national_address' => 'organization.national_address.self',
            $prefix.'category_ids' => 'organization.category_ids',
            $prefix.'category_ids.*' => 'organization.category_ids',
            $prefix.'visible_in_suggestions' => 'organization.visible_in_suggestions',
        ];

        foreach (array_keys(self::ADDRESS_COLUMNS) as $part) {
            $keys[$prefix.'national_address.'.$part] = 'organization.national_address.'.$part;
        }

        return $keys;
    }

    /**
     * Lowercases the region and category ids (API.md §0.6: ids are accepted in any case).
     *
     * @param  array<string, mixed>  $input  the organization part of the input
     * @return array<string, mixed>
     */
    public static function normalise(array $input): array
    {
        if (is_string($input['region_id'] ?? null)) {
            $input['region_id'] = strtolower($input['region_id']);
        }

        if (is_array($input['category_ids'] ?? null)) {
            $input['category_ids'] = array_map(
                static fn (mixed $id): mixed => is_string($id) ? strtolower($id) : $id,
                $input['category_ids'],
            );
        }

        return $input;
    }

    /**
     * Maps the validated organization input to columns. Only sent keys are included.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function toData(array $validated): OrganizationProfileData
    {
        $attributes = Arr::only($validated, self::PLAIN_COLUMNS);

        if (is_string($validated['region_id'] ?? null)) {
            $attributes['region_id'] = Region::query()->wherePublicId($validated['region_id'])->value('id');
        }

        if (array_key_exists('national_address', $validated)) {
            $address = is_array($validated['national_address']) ? $validated['national_address'] : [];

            foreach (self::ADDRESS_COLUMNS as $key => $column) {
                // A null national_address clears it; a partial object changes only its keys.
                if ($validated['national_address'] === null || array_key_exists($key, $address)) {
                    $value = $address[$key] ?? null;
                    $attributes[$column] = is_string($value) && trim($value) !== '' ? trim($value) : null;
                }
            }
        }

        foreach (['name', 'city', 'legal_name_ar', 'legal_name_en', 'website'] as $column) {
            if (is_string($attributes[$column] ?? null)) {
                $trimmed = trim($attributes[$column]);
                $attributes[$column] = $trimmed === '' && $column !== 'name' && $column !== 'city' ? null : $trimmed;
            }
        }

        $categoryIds = null;

        if (array_key_exists('category_ids', $validated)) {
            $publicIds = is_array($validated['category_ids']) ? $validated['category_ids'] : [];
            $categoryIds = $publicIds === []
                ? []
                : Category::query()->whereIn('public_id', $publicIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        }

        return new OrganizationProfileData($attributes, $categoryIds);
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
