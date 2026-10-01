<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Http\Controllers\AppV1;

use App\Modules\Competitions\Actions\ClaimInvitation;
use App\Modules\Competitions\Actions\DeclineInvitation;
use App\Modules\Competitions\Actions\JoinCompetition;
use App\Modules\Competitions\Actions\LookupInvitation;
use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Enums\ViewerRole;
use App\Modules\Competitions\Http\Controllers\Concerns\ActsForCaller;
use App\Modules\Competitions\Http\Requests\ClaimInvitationRequest;
use App\Modules\Competitions\Http\Requests\DeclineInvitationByTokenRequest;
use App\Modules\Competitions\Http\Requests\DeclineInvitationRequest;
use App\Modules\Competitions\Http\Requests\JoinCompetitionRequest;
use App\Modules\Competitions\Http\Requests\LookupInvitationRequest;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\CompetitionPresenter;
use App\Modules\Competitions\Services\InvitationTokens;
use App\Modules\Competitions\Services\ViewerResolver;
use App\Support\Exceptions\ApiException;
use App\Support\Http\Controllers\ApiController;
use App\Support\Http\Iso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The invitee side (API.md §1.5, ARCHITECTURE §13.11): token lookup and decline for guests,
 * claim, join and decline for signed-in users. Tokens travel in the JSON body only.
 */
final class InvitationController extends ApiController
{
    use ActsForCaller;

    public function __construct(
        private readonly InvitationTokens $tokens,
        private readonly CompetitionPresenter $presenter,
        private readonly ViewerResolver $viewers,
    ) {}

    public function lookup(LookupInvitationRequest $request, LookupInvitation $lookup): JsonResponse
    {
        $result = $lookup->handle($request->token(), $this->actor());
        $invitation = $result->invitation;
        $viewer = new Viewer(ViewerRole::Invitee, $invitation->organization_id ?? 0, invitation: $invitation);

        return $this->ok([
            'invitation' => [
                'id' => $invitation->public_id,
                'status' => $invitation->status->value,
                'email_masked' => InvitationTokens::maskEmail($invitation->email),
                'join_deadline' => Iso::format($result->competition->invitation_cutoff_at),
                'sponsored' => $result->sponsored,
            ],
            'competition' => $this->presenter->teaser($result->competition, $viewer, null, guest: true),
            'next_step' => $result->nextStep,
        ]);
    }

    public function declineByToken(DeclineInvitationByTokenRequest $request, DeclineInvitation $decline): JsonResponse
    {
        $invitation = $decline->handle($this->tokens->find($request->token()), $request->reason(), $this->actor());

        return $this->ok(['status' => $invitation->status->value]);
    }

    public function claim(ClaimInvitationRequest $request, ClaimInvitation $claim): JsonResponse
    {
        $user = $this->user($request) ?? throw new ApiException(errorCode: 'unauthenticated', status: 401);
        $result = $claim->handle($request->token(), $request->code(), $user, $this->actor());

        if (! $result->isBound() || $result->invitation === null) {
            return $this->ok(['otp_sent_to' => $result->otpSentTo, 'otp_expires_at' => Iso::format($result->otpExpiresAt)], status: 202);
        }

        return $this->ok($this->inviteeView($request, $result->invitation));
    }

    public function join(JoinCompetitionRequest $request, Invitation $invitation, JoinCompetition $join): JsonResponse
    {
        $this->assertOwnInvitation($invitation);

        $user = $this->user($request) ?? throw new ApiException(errorCode: 'unauthenticated', status: 401);
        $join->handle($invitation, $request->acceptsTerms(), $user->id, $this->actor());

        $competition = $invitation->competition()->firstOrFail();

        return $this->ok($this->presenter->present($competition, $this->viewers->for($competition, $this->actor()), $user));
    }

    public function decline(DeclineInvitationRequest $request, Invitation $invitation, DeclineInvitation $decline): JsonResponse
    {
        $this->assertOwnInvitation($invitation);

        return $this->ok($this->inviteeView($request, $decline->handle($invitation, $request->reason(), $this->actor())));
    }

    /**
     * Invitation (invitee view, API.md §2.7): `{id, status, join_deadline, sent_at, competition}`.
     *
     * @return array<string, mixed>
     */
    private function inviteeView(Request $request, Invitation $invitation): array
    {
        $competition = $invitation->competition()->firstOrFail();
        $viewer = new Viewer(ViewerRole::Invitee, (int) $invitation->organization_id, invitation: $invitation);

        return [
            'id' => $invitation->public_id,
            'status' => $invitation->status->value,
            'join_deadline' => Iso::format($competition->invitation_cutoff_at),
            'sent_at' => Iso::format($invitation->sent_at),
            'competition' => $this->presenter->teaser($competition, $viewer, $this->user($request)),
        ];
    }

    /**
     * Only the invited organization may act on its invitation; anyone else gets 404.
     */
    private function assertOwnInvitation(Invitation $invitation): void
    {
        if ($invitation->organization_id === null || $invitation->organization_id !== $this->actor()->organizationId) {
            throw ViewerResolver::notFound();
        }
    }
}
