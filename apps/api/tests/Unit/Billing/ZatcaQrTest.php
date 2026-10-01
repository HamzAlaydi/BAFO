<?php

declare(strict_types=1);

use App\Modules\Billing\Services\EInvoicing\ZatcaQr;

it('encodes the ZATCA TLV fields as base64 and decodes them back', function () {
    $fields = [
        1 => 'بافو',
        2 => '300000000000003',
        3 => '2026-10-01T09:00:00Z',
        4 => '1725.00',
        5 => '225.00',
    ];

    $payload = ZatcaQr::encode($fields);
    $binary = base64_decode($payload, true);

    expect($binary)->not->toBeFalse()
        ->and(ord($binary[0]))->toBe(1)
        ->and(ord($binary[1]))->toBe(strlen('بافو'))
        ->and(ZatcaQr::decode($payload))->toBe($fields);
});

it('formats halalas as decimal strings', function (int $minor, string $expected) {
    expect(ZatcaQr::amount($minor))->toBe($expected);
})->with([
    [172_500, '1725.00'],
    [22_505, '225.05'],
    [7, '0.07'],
    [0, '0.00'],
]);
