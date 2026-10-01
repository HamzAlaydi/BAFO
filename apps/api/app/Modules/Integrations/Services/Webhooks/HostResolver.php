<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

/**
 * Resolves a host name to its A and AAAA addresses for the SSRF guard (ARCHITECTURE §14.6).
 * Bound to SystemHostResolver; tests bind a fake.
 */
interface HostResolver
{
    /**
     * @return list<string> IPv4 and IPv6 addresses; empty when the name does not resolve
     */
    public function resolve(string $host): array;
}
