<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

/**
 * DNS resolution through the system resolver (A and AAAA records).
 */
final class SystemHostResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        $addresses = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        foreach (is_array($records) ? $records : [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            // Hosts-file names (for example `localhost`) have no DNS record.
            $fallback = gethostbynamel($host);
            $addresses = is_array($fallback) ? $fallback : [];
        }

        return array_values(array_unique($addresses));
    }
}
