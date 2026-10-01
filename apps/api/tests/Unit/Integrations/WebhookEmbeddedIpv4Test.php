<?php

declare(strict_types=1);

use App\Modules\Integrations\Services\Webhooks\WebhookUrlGuard;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-09: IPv6 forms that carry an IPv4 address
 * (NAT64 64:ff9b::/96 and 64:ff9b:1::/48, 6to4 2002::/16, IPv4-compatible ::/96) are judged by that
 * IPv4 address, so a webhook host resolving to 64:ff9b::a9fe:a9fe cannot reach the cloud metadata
 * service through a NAT64 gateway.
 */

it('blocks IPv6 addresses that embed a private IPv4 address', function (string $address) {
    expect(WebhookUrlGuard::isBlockedAddress($address))->toBeTrue();
})->with([
    'NAT64 metadata' => '64:ff9b::a9fe:a9fe',
    'NAT64 loopback' => '64:ff9b::7f00:1',
    'NAT64 local-use prefix' => '64:ff9b:1::a00:1',
    '6to4 loopback' => '2002:7f00:1::1',
    '6to4 private' => '2002:c0a8:101::1',
    'IPv4-compatible loopback' => '::127.0.0.1',
    'IPv4-compatible metadata' => '::169.254.169.254',
]);

it('still allows IPv6 addresses that embed a public IPv4 address', function (string $address) {
    expect(WebhookUrlGuard::isBlockedAddress($address))->toBeFalse();
})->with([
    'NAT64 public' => '64:ff9b::808:808',
    '6to4 public' => '2002:808:808::1',
]);
