<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccountGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The account gate of ARCHITECTURE §8.1, appended to the `app_v1` group: 403
 * `account_inactive`, `email_not_verified` or `organization_suspended`. It passes through guest
 * requests (and any authenticated model that is not the Identity user) and the exempt routes:
 * logout, `GET /me` and the account deletion endpoints.
 */
final readonly class EnsureAccountActive
{
    /**
     * @var list<string>
     */
    public const array EXEMPT_ROUTES = ['app.v1.auth.logout', 'app.v1.me.show'];

    public const string EXEMPT_PREFIX = 'app.v1.account.deletion.';

    public function __construct(private AccountGate $gate) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || self::isExempt($request)) {
            return $next($request);
        }

        $user->loadMissing('membership.organization');

        $blocker = $this->gate->blockerFor($user);

        if ($blocker !== null) {
            throw $blocker;
        }

        return $next($request);
    }

    private static function isExempt(Request $request): bool
    {
        $name = $request->route()?->getName();

        return is_string($name) && (in_array($name, self::EXEMPT_ROUTES, true) || str_starts_with($name, self::EXEMPT_PREFIX));
    }
}
