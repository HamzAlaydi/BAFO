<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Requests\OrganizationProfileFields;
use Tests\TestCase;

uses(TestCase::class);

it('maps the national address to the organization columns', function () {
    $data = OrganizationProfileFields::toData([
        'name' => '  شركة المصدر ',
        'website' => '',
        'national_address' => ['building_number' => '1234', 'short_address' => 'RRRD2929', 'street' => ' '],
    ]);

    expect($data->attributes)->toBe([
        'name' => 'شركة المصدر',
        'website' => null,
        'address_building_number' => '1234',
        'address_street' => null,
        'address_short' => 'RRRD2929',
    ])->and($data->categoryIds)->toBeNull();
});

it('clears the whole address when it is sent as null', function () {
    $data = OrganizationProfileFields::toData(['national_address' => null]);

    expect($data->attributes)->toBe(array_fill_keys(array_values(OrganizationProfileFields::ADDRESS_COLUMNS), null));
});

it('reads an empty category list as "remove all"', function () {
    expect(OrganizationProfileFields::toData(['category_ids' => []])->categoryIds)->toBe([])
        ->and(OrganizationProfileFields::toData(['category_ids' => null])->categoryIds)->toBe([]);
});

it('lowercases the ids of the input', function () {
    expect(OrganizationProfileFields::normalise(['region_id' => '01J9ZQ', 'category_ids' => ['01ABC', 7]]))
        ->toBe(['region_id' => '01j9zq', 'category_ids' => ['01abc', 7]]);
});
