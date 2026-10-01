<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Policies;

use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\CompetitionPermissions;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Support\Auth\CurrentActor;
use Illuminate\Auth\Access\Response;

/**
 * App v1 authorization of competitions (ARCHITECTURE §8.2, §8.3). Someone with no view of the
 * competition gets 404 (existence is never revealed); a viewer without the right gets 403.
 * State rules are the Actions' (409 / 422).
 */
final readonly class CompetitionPolicy
{
    public function __construct(private ViewerResolver $viewers) {}

    public function create(User $user): Response
    {
        return $user->hasPermission(Permission::CompetitionsCreate) ? Response::allow() : Response::deny();
    }

    public function view(User $user, Competition $competition): Response
    {
        return $this->viewer($competition) !== null ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Any member of the issuer organization (invitations list, issuer-only reads).
     */
    public function viewAsIssuer(User $user, Competition $competition): Response
    {
        return $this->issuer($competition, static fn (): bool => true);
    }

    /**
     * `competitions.manage` on the competition: edit, delete, publish, invite, attachments,
     * suggestions, extend, cancel.
     */
    public function manage(User $user, Competition $competition): Response
    {
        return $this->issuer($competition, static fn (): bool => CompetitionPermissions::canManage($user, $competition));
    }

    /**
     * `competitions.award`: close without award.
     */
    public function award(User $user, Competition $competition): Response
    {
        return $this->issuer($competition, static fn (): bool => CompetitionPermissions::canAward($user));
    }

    /**
     * Issuer members and participants (Q&A).
     */
    public function discuss(User $user, Competition $competition): Response
    {
        $viewer = $this->viewer($competition);

        if ($viewer === null) {
            return Response::denyAsNotFound();
        }

        return $viewer->isIssuer() || $viewer->isParticipant() ? Response::allow() : Response::deny();
    }

    /**
     * @param  callable(): bool  $allowed
     */
    private function issuer(Competition $competition, callable $allowed): Response
    {
        $viewer = $this->viewer($competition);

        if ($viewer === null) {
            return Response::denyAsNotFound();
        }

        return $viewer->isIssuer() && $allowed() ? Response::allow() : Response::deny();
    }

    private function viewer(Competition $competition): ?Viewer
    {
        return $this->viewers->resolve($competition, CurrentActor::get());
    }
}
