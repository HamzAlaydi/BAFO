<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Console\Commands\ExecuteAccountDeletionsCommand;
use App\Modules\Identity\Console\Commands\PruneIdentityCommand;
use App\Modules\Identity\Contracts\OtpCodes;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Http\Middleware\EnsureAccountActive;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\MembershipPolicy;
use App\Modules\Identity\Services\OtpService;
use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FilePurpose;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Identity module.
 *
 * Organizations, users and memberships (owner, admin, member; can_award / can_purchase),
 * the Permission enum, OTP, register, login and logout (Sanctum tokens), password reset,
 * /me and profile, organization profile and files, team members, consents and account
 * deletion.
 *
 * HTTP routes: routes/app_v1/identity.php and routes/public_v1/identity.php.
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class IdentityServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Membership::class => MembershipPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(OtpService::class);
        $this->app->singleton(OtpCodes::class, OtpService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // §2.2 item 7: the account gate runs on every app v1 request (it passes guests through).
        $this->app->make(Router::class)->pushMiddlewareToGroup('app_v1', EnsureAccountActive::class);

        // §8.1: `perm` checks one organization permission, e.g. can:perm,'team.manage'.
        Gate::define('perm', static fn (User $user, string $permission): bool => ($case = Permission::tryFrom($permission)) !== null
            && $user->hasPermission($case));

        $this->registerFileAccessRules();

        // SECURITY_REVIEW S-11: endpoints that check the current password (change password,
        // account deletion) are not a password-guessing oracle for a stolen token.
        RateLimiter::for('password-confirm', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by('password-confirm:'.($request->user()?->getAuthIdentifier() ?? 'ip:'.$request->ip())));

        if ($this->app->runningInConsole()) {
            $this->commands([PruneIdentityCommand::class, ExecuteAccountDeletionsCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('identity:prune')->daily()->onOneServer();
            $schedule->command('identity:execute-account-deletions')->hourly()->withoutOverlapping()->onOneServer();
        });
    }

    /**
     * §8.5 `organization_profile`: members of the owning organization, and members of an issuer
     * organization with a competition in which the owner organization is a participant.
     */
    private function registerFileAccessRules(): void
    {
        $this->app->make(FileAccessRegistry::class)->register(
            FilePurpose::OrganizationProfile,
            static function (Authenticatable $user, File $file): bool {
                if (! $user instanceof User || $file->organization_id === null) {
                    return false;
                }

                $membership = $user->membership;

                if ($membership === null || $membership->status !== MembershipStatus::Active) {
                    return false;
                }

                if ($membership->organization_id === $file->organization_id) {
                    return true;
                }

                return Participant::query()
                    ->where('organization_id', $file->organization_id)
                    ->whereHas('competition', static fn ($query) => $query->where('organization_id', $membership->organization_id))
                    ->exists();
            },
        );
    }
}
