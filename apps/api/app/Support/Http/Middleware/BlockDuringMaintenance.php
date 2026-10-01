<?php

declare(strict_types=1);

namespace App\Support\Http\Middleware;

use App\Support\Exceptions\ApiException;
use App\Support\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-controlled maintenance for the apps (ARCHITECTURE §4.8): while the setting
 * `app.maintenance.enabled` is true, app v1 answers 503 `maintenance` with the localised
 * `app.maintenance.message` (or the generic message when it is empty). GET /app-config,
 * /time and /health stay available so clients can show the maintenance screen.
 */
final readonly class BlockDuringMaintenance
{
    /**
     * @var list<string>
     */
    public const array EXEMPT_ROUTES = ['app.v1.app-config', 'app.v1.time', 'app.v1.health'];

    // CONTRACT-GAP: API.md §0.3 sends Retry-After on 503 without a value; clients poll
    // /app-config, so a short hint is enough.
    public const int RETRY_AFTER_SECONDS = 300;

    public function __construct(private Settings $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->settings->get('app.maintenance.enabled', false) !== true || $request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        throw new ApiException(
            errorCode: 'maintenance',
            messageKey: $this->message(),
            status: 503,
            headers: ['Retry-After' => (string) self::RETRY_AFTER_SECONDS],
        );
    }

    /**
     * The admin's message in the request locale, or null for the generic `errors.maintenance`.
     */
    private function message(): ?string
    {
        $messages = $this->settings->get('app.maintenance.message', []);
        $message = is_array($messages) ? ($messages[App::getLocale()] ?? null) : null;

        return is_string($message) && trim($message) !== '' ? $message : null;
    }
}
