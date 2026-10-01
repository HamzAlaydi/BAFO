<?php

declare(strict_types=1);

namespace App\Modules\Competitions;

use App\Modules\Competitions\Console\Commands\CompetitionsTickCommand;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Contracts\CompetitionTimingService;
use App\Modules\Competitions\Events\AttachmentAdded;
use App\Modules\Competitions\Events\CommentPosted;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Events\InvitationJoined;
use App\Modules\Competitions\Events\InvitationRevoked;
use App\Modules\Competitions\Events\InvitationSent;
use App\Modules\Competitions\Events\InvitationsExpired;
use App\Modules\Competitions\Events\InvitationViewed;
use App\Modules\Competitions\Listeners\AttachPendingInvitations;
use App\Modules\Competitions\Listeners\BroadcastCommentCreated;
use App\Modules\Competitions\Listeners\BroadcastCompetitionUpdated;
use App\Modules\Competitions\Listeners\BroadcastInvitationUpdated;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Policies\CompetitionPolicy;
use App\Modules\Competitions\Services\AttachmentAccess;
use App\Modules\Competitions\Services\CompetitionTiming;
use App\Modules\Competitions\Services\StateMachine;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Models\User;
use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FilePurpose;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Settings\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;

/**
 * Competitions module.
 *
 * Competitions and their rules, the lifecycle state machine and scheduler (publish,
 * extend, cancel, close without award), invitations (send, claim, join, decline, revoke,
 * expire), suggestions, attachments, Q&A and home stats.
 *
 * Contracts bound here (ARCHITECTURE §3.6): CompetitionStateMachine, CompetitionTimingService.
 *
 * HTTP routes: routes/app_v1/competitions.php and routes/public_v1/competitions.php.
 * Conventions (config, migrations, views, web routes): App\Support\Modules\ModuleServiceProvider.
 */
final class CompetitionsServiceProvider extends ModuleServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Competition::class => CompetitionPolicy::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(CompetitionStateMachine::class, StateMachine::class);
        $this->app->singleton(CompetitionTimingService::class, CompetitionTiming::class);
    }

    public function boot(): void
    {
        parent::boot();

        /** @var array<string, mixed> $defaults */
        $defaults = config('bafo.competitions.settings', []);
        $this->app->make(Settings::class)->defaults($defaults);

        $this->app->make(FileAccessRegistry::class)->register(
            FilePurpose::CompetitionAttachment,
            fn (Authenticatable $user, File $file): bool => $user instanceof User
                && $this->app->make(AttachmentAccess::class)->allows($user, $file),
        );

        Event::listen(CompetitionUpdated::class, BroadcastCompetitionUpdated::class);
        Event::listen(AttachmentAdded::class, BroadcastCompetitionUpdated::class);
        Event::listen(CommentPosted::class, BroadcastCommentCreated::class);

        foreach ([InvitationSent::class, InvitationViewed::class, InvitationJoined::class, InvitationDeclined::class,
            InvitationRevoked::class, InvitationsExpired::class] as $event) {
            Event::listen($event, BroadcastInvitationUpdated::class);
        }

        Event::listen(EmailVerified::class, AttachPendingInvitations::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CompetitionsTickCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('competitions:tick')->everyTenSeconds()->withoutOverlapping()->onOneServer();
        });
    }
}
