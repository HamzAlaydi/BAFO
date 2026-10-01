<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\AppV1;

use App\Modules\Identity\Actions\AddTeamMember;
use App\Modules\Identity\Actions\RemoveTeamMember;
use App\Modules\Identity\Actions\ResendTeamInvitation;
use App\Modules\Identity\Actions\UpdateTeamMember;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Http\Requests\ListTeamMembersRequest;
use App\Modules\Identity\Http\Requests\StoreTeamMemberRequest;
use App\Modules\Identity\Http\Requests\UpdateTeamMemberRequest;
use App\Modules\Identity\Http\Resources\MembershipResource;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Services\Entitlements;
use App\Support\Auth\CurrentActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Team members (branch users), API.md §1.3 and ARCHITECTURE §13.10. Every route needs
 * `team.manage`; memberships of other organizations are 404 (MembershipPolicy, checked by the
 * route `can:` middleware before validation).
 */
final class TeamMemberController extends IdentityController
{
    /**
     * Not paginated (≤ 50), with `meta.seats = {used, total}`. The owner comes first.
     */
    public function index(ListTeamMembersRequest $request, Entitlements $entitlements): JsonResponse
    {
        $organization = self::currentOrganization($request);
        $status = $request->status();

        $memberships = Membership::query()
            ->where('organization_id', $organization->id)
            ->when($status !== null, static fn ($query) => $query->where('status', $status?->value))
            ->with('user.avatarFile')
            ->orderByRaw('case when role = ? then 0 else 1 end', [OrgRole::Owner->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return $this->ok(MembershipResource::collection($memberships), [
            'seats' => [
                'used' => $entitlements->seatsUsed($organization),
                'total' => $entitlements->seatLimit($organization),
            ],
        ]);
    }

    public function store(StoreTeamMemberRequest $request, AddTeamMember $add): JsonResponse
    {
        $membership = $add->handle(
            self::currentOrganization($request),
            $request->memberData(),
            self::currentUser($request),
            CurrentActor::get(),
        );

        return $this->created(new MembershipResource($membership->load('user.avatarFile')));
    }

    public function update(UpdateTeamMemberRequest $request, Membership $membership, UpdateTeamMember $update): JsonResponse
    {
        $membership = $update->handle($membership, $request->changes(), self::currentUser($request), CurrentActor::get());

        return $this->ok(new MembershipResource($membership));
    }

    public function destroy(Request $request, Membership $membership, RemoveTeamMember $remove): JsonResponse
    {
        $remove->handle($membership, self::currentUser($request), CurrentActor::get());

        return $this->noContent();
    }

    public function resend(Request $request, Membership $membership, ResendTeamInvitation $resend): JsonResponse
    {
        $resend->handle($membership, self::currentUser($request), CurrentActor::get());

        return $this->noContent();
    }
}
