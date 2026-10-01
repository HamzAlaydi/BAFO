<?php

declare(strict_types=1);

use App\Modules\Integrations\Services\Webhooks\WebhookSigner;

it('generates whsec_ secrets of 32 random bytes', function () {
    $secret = WebhookSigner::generateSecret();

    expect($secret)->toStartWith('whsec_')
        ->and(strlen((string) base64_decode(substr($secret, 6), true)))->toBe(32)
        ->and(WebhookSigner::generateSecret())->not->toBe($secret);
});

it('signs the Standard Webhooks content with the decoded secret', function () {
    $secret = 'whsec_'.base64_encode(str_repeat("\x01", 32));
    $expected = 'v1,'.base64_encode(hash_hmac('sha256', 'msg_1.1794215564.{"a":1}', str_repeat("\x01", 32), true));

    expect(WebhookSigner::sign($secret, 'msg_1', 1794215564, '{"a":1}'))->toBe($expected);
});

it('verifies any matching signature within the tolerance', function () {
    $secret = WebhookSigner::generateSecret();
    $signature = WebhookSigner::sign($secret, 'msg_1', 1000, 'body');

    expect(WebhookSigner::verify($secret, 'msg_1', '1000', $signature, 'body', 1200))->toBeTrue()
        ->and(WebhookSigner::verify($secret, 'msg_1', '1000', 'v1,bm90LWl0 '.$signature, 'body', 1000))->toBeTrue()
        ->and(WebhookSigner::verify($secret, 'msg_1', '1000', $signature, 'body!', 1000))->toBeFalse()
        ->and(WebhookSigner::verify($secret, 'msg_2', '1000', $signature, 'body', 1000))->toBeFalse()
        ->and(WebhookSigner::verify($secret, 'msg_1', '1000', $signature, 'body', 1301))->toBeFalse()
        ->and(WebhookSigner::verify(WebhookSigner::generateSecret(), 'msg_1', '1000', $signature, 'body', 1000))->toBeFalse()
        ->and(WebhookSigner::verify($secret, 'msg_1', 'soon', $signature, 'body', 1000))->toBeFalse()
        ->and(WebhookSigner::verify($secret, 'msg_1', '1000', substr($signature, 3), 'body', 1000))->toBeFalse();
});
