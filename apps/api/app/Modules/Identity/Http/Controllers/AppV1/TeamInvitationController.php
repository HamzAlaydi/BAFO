<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\AcceptTeamInvitation;
use App\Modules\Identity\Exceptions\IdentityError;
use App\Modules\Identity\Http\Requests\AcceptTeamInvitationRequest;
use App\Modules\Identity\Http\Requests\LookupTeamInvitationRequest;
use App\Modules\Identity\Http\Resources\MeResource;
use App\Modules\Identity\Http\Resources\OrganizationResource;
use App\Modules\Identity\Services\TeamInvitationTokens;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Iso;
use Illuminate\Http\JsonResponse;

/**
 * Team invitation links (guest, `auth`), ARCHITECTURE §13.10. The token comes from the URL
 * fragment and travels in the JSON body only.
 *
 *   POST /auth/team-invitations/lookup   {email, name, organization {name, logo_url}, role, expires_at}
 *   POST /auth/team-invitations/accept   AuthTokenPayload
 */
final class TeamInvitationController extends IdentityController
{
    public function lookup(LookupTeamInvitationRequest $request, TeamInvitationTokens $tokens): JsonResponse
    {
        $membership = $tokens->find($request->token()) ?? throw IdentityError::make('team_invitation_invalid');
        $membership->loadMissing(['user', 'organization.logoFile']);

        return $this->ok([
            'email' => $membership->user->email,
            'name' => $membership->user->name,
            'organization' => [
                'name' => OrganizationResource::displayName($membership->organization),
                'logo_url' => OrganizationResource::logoUrl($membership->organization),
            ],
            'role' => $membership->role->value,
            'expires_at' => Iso::format($membership->invite_expires_at),
        ]);
    }

    public function accept(AcceptTeamInvitationRequest $request, AcceptTeamInvitation $accept): JsonResponse
    {
        $result = $accept->handle($request->token(), $request->password(), $request->deviceName(), CurrentActor::get());

        return $this->ok(new MeResource($result['user'], $result['token']));
    }
}
