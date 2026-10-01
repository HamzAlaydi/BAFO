<?php

declare(strict_types=1);

use App\Support\Auth\Channel;
use App\Support\Http\Middleware\EnsureSupportedAppVersion;

it('maps X-Platform to a channel', function (?string $header, Channel $channel) {
    expect(Channel::fromPlatformHeader($header))->toBe($channel);
})->with([
    ['ios', Channel::Ios],
    [' Android ', Channel::Android],
    ['web', Channel::Web],
    ['windows', Channel::Web],
    [null, Channel::Web],
]);

it('parses app versions', function (mixed $version, ?array $expected) {
    expect(EnsureSupportedAppVersion::parse($version))->toBe($expected);
})->with([
    ['1.2.3', [1, 2, 3]],
    ['v2.0', [2, 0, 0]],
    ['3', [3, 0, 0]],
    ['1.4.0-beta.1', [1, 4, 0]],
    ['1.4.0+45', [1, 4, 0]],
    ['latest', null],
    ['', null],
    [null, null],
    ['1.2.3.4', null],
]);
