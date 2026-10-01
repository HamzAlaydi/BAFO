<?php

declare(strict_types=1);

namespace Tests\Support\Integrations;

use App\Modules\Integrations\Services\Webhooks\HostResolver;

/**
 * DNS for tests: every host resolves to a public documentation address unless mapped.
 */
final class FakeHostResolver implements HostResolver
{
    public const string PUBLIC_IP = '203.0.113.10';

    /**
     * @param  array<string, list<string>>  $hosts
     */
    public function __construct(private array $hosts = []) {}

    /**
     * @param  list<string>  $addresses
     */
    public function map(string $host, array $addresses): self
    {
        $this->hosts[strtolower($host)] = $addresses;

        return $this;
    }

    public function resolve(string $host): array
    {
        return $this->hosts[strtolower($host)] ?? [self::PUBLIC_IP];
    }
}
