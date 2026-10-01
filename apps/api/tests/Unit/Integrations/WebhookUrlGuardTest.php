<?php

declare(strict_types=1);

use App\Modules\Integrations\Services\Webhooks\BlockedWebhookTarget;
use App\Modules\Integrations\Services\Webhooks\WebhookUrlGuard;
use Tests\Support\Integrations\FakeHostResolver;
use Tests\TestCase;

uses(TestCase::class);

function integrationsGuard(array $hosts = [], bool $allowPrivate = false): WebhookUrlGuard
{
    config(['bafo.integrations.webhooks.allow_private_targets' => $allowPrivate]);

    return new WebhookUrlGuard(new FakeHostResolver($hosts), app());
}

it('blocks every private, loopback, link-local, shared and multicast address', function (string $address) {
    expect(WebhookUrlGuard::isBlockedAddress($address))->toBeTrue();
})->with([
    '0.0.0.0', '0.1.2.3', '10.0.0.1', '10.255.255.255', '100.64.0.1', '100.127.255.254', '127.0.0.1', '127.8.8.8',
    '169.254.169.254', '172.16.0.1', '172.31.255.255', '192.168.0.1', '192.168.255.255', '224.0.0.1', '239.255.255.250',
    '255.255.255.255', '::', '::1', 'fc00::1', 'fd12:3456::1', 'fe80::1', 'febf::1', 'ff02::1', '::ffff:127.0.0.1',
    '::ffff:10.1.2.3', 'not-an-ip',
]);

it('allows public addresses', function (string $address) {
    expect(WebhookUrlGuard::isBlockedAddress($address))->toBeFalse();
})->with([
    '8.8.8.8', '1.1.1.1', '100.63.255.255', '100.128.0.1', '172.15.255.255', '172.32.0.1', '192.167.1.1', '203.0.113.10',
    '2001:4860:4860::8888', '2606:4700::1111', '::ffff:8.8.8.8',
]);

it('pins the first checked address and keeps the host for TLS', function () {
    $target = integrationsGuard(['erp.example.sa' => ['203.0.113.7', '2001:db8::7']])->check('https://erp.example.sa:8443/hooks?x=1');

    expect($target->host)->toBe('erp.example.sa')
        ->and($target->port)->toBe(8443)
        ->and($target->ip)->toBe('203.0.113.7')
        ->and($target->curlResolveEntry())->toBe('erp.example.sa:8443:203.0.113.7');

    expect(integrationsGuard(['v6.example.sa' => ['2001:4860::1']])->check('https://v6.example.sa/h')->curlResolveEntry())
        ->toBe('v6.example.sa:443:[2001:4860::1]');
});

it('refuses URLs by scheme, credentials, port and resolution', function (string $url, string $reason, array $hosts) {
    expect(fn () => integrationsGuard($hosts)->check($url))
        ->toThrow(fn (BlockedWebhookTarget $e) => expect($e->reason)->toBe($reason));
})->with([
    ['http://erp.example.sa/h', 'scheme', []],
    ['ftp://erp.example.sa/h', 'scheme', []],
    ['https://a:b@erp.example.sa/h', 'credentials', []],
    ['https://erp.example.sa:80/h', 'port', []],
    ['https://erp.example.sa:1023/h', 'port', []],
    ['https://nowhere.example.sa/h', 'unresolvable', ['nowhere.example.sa' => []]],
    ['https://internal.example.sa/h', 'private_address', ['internal.example.sa' => ['203.0.113.1', '10.0.0.1']]],
    ['https://[fe80::1]/h', 'private_address', []],
    ['not a url', 'host', []],
]);

it('accepts port 443 and 1024–65535', function (string $url) {
    expect(integrationsGuard()->check($url)->url)->toBe($url);
})->with(['https://erp.example.sa/h', 'https://erp.example.sa:443/h', 'https://erp.example.sa:1024/h', 'https://erp.example.sa:65535/h']);

it('relaxes the guard for local development only', function () {
    $hosts = ['localhost' => ['127.0.0.1']];

    expect(integrationsGuard($hosts, allowPrivate: true)->check('http://localhost:9999/webhooks')->ip)->toBe('127.0.0.1')
        ->and(integrationsGuard($hosts, allowPrivate: true)->check('http://localhost/webhooks')->port)->toBe(80);

    app()->detectEnvironment(fn () => 'production');

    expect(fn () => integrationsGuard($hosts, allowPrivate: true)->check('http://localhost:9999/webhooks'))
        ->toThrow(BlockedWebhookTarget::class);
});
