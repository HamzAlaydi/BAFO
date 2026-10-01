<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

/**
 * A webhook URL that passed the SSRF guard, with the address the request must connect to
 * (pinned against DNS rebinding, ARCHITECTURE §14.6).
 */
final readonly class WebhookTarget
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $ip,
    ) {}

    /**
     * The cURL `CURLOPT_RESOLVE` entry that pins the host to the checked address while keeping
     * the `Host` header and TLS name verification on the host name.
     */
    public function curlResolveEntry(): string
    {
        $ip = str_contains($this->ip, ':') ? '['.$this->ip.']' : $this->ip;

        return $this->host.':'.$this->port.':'.$ip;
    }
}
