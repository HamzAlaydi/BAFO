<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

use Illuminate\Contracts\Foundation\Application;

/**
 * The SSRF guard of ARCHITECTURE §14.6, run when an endpoint is created or updated and before
 * every send:
 *
 *   - the scheme is `https`, the URL carries no credentials, the port is 443 or 1024–65535;
 *   - every A/AAAA address of the host is public (RFC 1918, 100.64/10, 127/8, 169.254/16, 0/8,
 *     IPv4 multicast and reserved space, `::1`, `::`, `fc00::/7`, `fe80::/10`, IPv6 multicast, the
 *     NAT64 local-use prefix, and IPv4-mapped, IPv4-compatible, NAT64 and 6to4 forms of the
 *     blocked IPv4 ranges are refused);
 *   - the checked address is returned so the sender connects to it (pinning, no DNS rebinding).
 *
 * `WEBHOOKS_ALLOW_PRIVATE_TARGETS=true` outside production allows `http` (default port 80 too)
 * and private or loopback targets for local development.
 */
final readonly class WebhookUrlGuard
{
    /**
     * @var list<string>
     */
    private const array BLOCKED_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
        // NAT64 local-use prefix (RFC 8215): never a public host.
        '64:ff9b:1::/48',
    ];

    public function __construct(
        private HostResolver $resolver,
        private Application $app,
    ) {}

    /**
     * @throws BlockedWebhookTarget
     */
    public function check(string $url): WebhookTarget
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new BlockedWebhookTarget('host');
        }

        $allowPrivate = $this->allowsPrivateTargets();
        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'https' && ! ($allowPrivate && $scheme === 'http')) {
            throw new BlockedWebhookTarget('scheme');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new BlockedWebhookTarget('credentials');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (! self::portAllowed($port, $scheme, $allowPrivate)) {
            throw new BlockedWebhookTarget('port');
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === '' || preg_match('/[\s\/\\\\@]/', $host) === 1) {
            throw new BlockedWebhookTarget('host');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolver->resolve($host);

        if ($addresses === []) {
            throw new BlockedWebhookTarget('unresolvable');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP) === false || (! $allowPrivate && self::isBlockedAddress($address))) {
                throw new BlockedWebhookTarget('private_address');
            }
        }

        return new WebhookTarget($url, $host, $port, $addresses[0]);
    }

    public function allowsPrivateTargets(): bool
    {
        return config('bafo.integrations.webhooks.allow_private_targets') === true && ! $this->app->isProduction();
    }

    public static function isBlockedAddress(string $address): bool
    {
        $binary = @inet_pton($address);

        if ($binary === false) {
            return true;
        }

        // IPv6 forms that carry an IPv4 address are judged by that address (SECURITY_REVIEW S-09).
        if (strlen($binary) === 16) {
            $binary = self::embeddedIpv4($binary) ?? $binary;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if (self::inRange($binary, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The IPv4 address inside an IPv4-mapped (::ffff:a.b.c.d), IPv4-compatible (::a.b.c.d), NAT64
     * well-known prefix (64:ff9b::a.b.c.d) or 6to4 (2002:aabb:ccdd::) address, else null.
     */
    private static function embeddedIpv4(string $binary): ?string
    {
        return match (true) {
            str_starts_with($binary, str_repeat("\0", 10)."\xff\xff"),
            str_starts_with($binary, str_repeat("\0", 12)),
            str_starts_with($binary, "\x00\x64\xff\x9b".str_repeat("\0", 8)) => substr($binary, 12),
            str_starts_with($binary, "\x20\x02") => substr($binary, 2, 4),
            default => null,
        };
    }

    private static function portAllowed(int $port, string $scheme, bool $allowPrivate): bool
    {
        return $port === 443
            || ($port >= 1024 && $port <= 65535)
            || ($allowPrivate && $scheme === 'http' && $port === 80);
    }

    private static function inRange(string $binary, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $networkBinary = (string) inet_pton($network);

        if (strlen($networkBinary) !== strlen($binary)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if (strncmp($binary, $networkBinary, $bytes) !== 0) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($binary[$bytes]) & $mask) === (ord($networkBinary[$bytes]) & $mask);
    }
}
