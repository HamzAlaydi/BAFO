<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Support\Auth\Channel;
use App\Support\Exceptions\ApiException;
use App\Support\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force update for the mobile apps (ARCHITECTURE §4.8). Only when X-Platform is `ios` or
 * `android`: if X-App-Version is below the setting `app.min_version.{platform}` the request
 * fails with 426 `app_version_unsupported` (`details.min_version`).
 *
 * GET /app-config, /time and /health are never blocked (API.md §1.1), so an outdated app can
 * still learn about the update.
 *
 * CONTRACT-GAP: a missing or non-semver X-App-Version is not blocked (it cannot be compared);
 * the mobile client always sends it.
 */
final readonly class EnsureSupportedAppVersion
{
    public const string PLATFORM_HEADER = 'X-Platform';

    public const string VERSION_HEADER = 'X-App-Version';

    /**
     * Route names that stay reachable for any client version.
     *
     * @var list<string>
     */
    public const array EXEMPT_ROUTES = ['app.v1.app-config', 'app.v1.time', 'app.v1.health'];

    public function __construct(private Settings $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $channel = Channel::fromPlatformHeader($request->header(self::PLATFORM_HEADER));

        if (! $channel->isMobile() || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        $version = self::parse($request->header(self::VERSION_HEADER));
        $minimum = (string) $this->settings->get('app.min_version.'.$channel->value, '1.0.0');
        $required = self::parse($minimum);

        if ($version !== null && $required !== null && $version < $required) {
            throw new ApiException('app_version_unsupported', status: 426, details: ['min_version' => $minimum]);
        }

        return $next($request);
    }

    /**
     * "1.2.3", "1.2" or "v1.2.3-beta+5" → [1, 2, 3]; anything else → null. Pre-release and
     * build suffixes are ignored.
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    public static function parse(mixed $version): ?array
    {
        if (! is_string($version) || preg_match('/^v?(\d{1,6})(?:\.(\d{1,6}))?(?:\.(\d{1,6}))?(?:[-+][0-9A-Za-z.+-]*)?$/', trim($version), $m) !== 1) {
            return null;
        }

        return [(int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0)];
    }
}
