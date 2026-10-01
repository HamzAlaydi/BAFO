<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\Coverage;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Http\Resources\Shapes;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Integrations\Models\ExternalRef;
use App\Support\Http\Iso;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The Invitation shapes of API.md §2.7 (issuer view) and §3.4 (PublicInvitation). Coverage comes
 * from Billing's `AccessPolicy::coverageFor()`; `pass_status` is the invitation's latest pass.
 */
final readonly class InvitationPresenter
{
    public function __construct(private AccessPolicy $access) {}

    /**
     * @param  Collection<int, Invitation>  $invitations
     * @return list<array<string, mixed>>
     */
    public function issuerViews(Collection $invitations): array
    {
        [$coverage, $passes] = $this->billing($invitations);

        return $invitations->map(fn (Invitation $invitation): array => $this->issuerView($invitation, $coverage, $passes))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function issuerViewOf(Invitation $invitation): array
    {
        return $this->issuerViews(new Collection([$invitation]))[0];
    }

    /**
     * @param  Collection<int, Invitation>  $invitations
     * @return list<array<string, mixed>>
     */
    public function publicViews(Collection $invitations): array
    {
        [$coverage, $passes] = $this->billing($invitations);

        return $invitations->map(static fn (Invitation $invitation): array => [
            'id' => $invitation->public_id,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'status' => $invitation->status->value,
            'organization' => $invitation->organization !== null
                ? ['id' => $invitation->organization->public_id, 'name' => $invitation->organization->name]
                : null,
            'vendor' => $invitation->vendor !== null ? [
                'id' => $invitation->vendor->public_id,
                'name' => $invitation->vendor->name,
                'external_refs' => $invitation->vendor->externalRefs->map(static fn (ExternalRef $ref): array => [
                    'system' => $ref->system, 'type' => $ref->type, 'id' => $ref->value, 'number' => $ref->number, 'url' => $ref->url,
                ])->values()->all(),
            ] : null,
            'sponsored' => ($coverage[$invitation->id] ?? null) === Coverage::Sponsored,
            'pass_status' => $passes[$invitation->id] ?? null,
            'participant' => $invitation->participant !== null ? [
                'id' => $invitation->participant->public_id,
                'alias_no' => $invitation->participant->alias_no,
                'joined_at' => Iso::format($invitation->participant->created_at),
            ] : null,
            'sent_at' => Iso::format($invitation->sent_at),
            'joined_at' => Iso::format($invitation->joined_at),
            'declined_at' => Iso::format($invitation->declined_at),
            'revoked_at' => Iso::format($invitation->revoked_at),
            'expired_at' => Iso::format($invitation->expired_at),
            'updated_at' => Iso::format($invitation->updated_at),
        ])->values()->all();
    }

    /**
     * @param  array<int, Coverage>  $coverage
     * @param  array<int, string>  $passes
     * @return array<string, mixed>
     */
    private function issuerView(Invitation $invitation, array $coverage, array $passes): array
    {
        return [
            'id' => $invitation->public_id,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'status' => $invitation->status->value,
            'organization' => Shapes::organization($invitation->organization),
            'vendor' => $invitation->vendor !== null ? ['id' => $invitation->vendor->public_id, 'name' => $invitation->vendor->name] : null,
            'sponsored_requested' => $invitation->sponsored_requested,
            'coverage' => ($coverage[$invitation->id] ?? Coverage::None)->value,
            'pass_status' => $passes[$invitation->id] ?? null,
            'participant' => $invitation->participant !== null ? [
                'id' => $invitation->participant->public_id,
                'alias_no' => $invitation->participant->alias_no,
                'joined_at' => Iso::format($invitation->participant->created_at),
            ] : null,
            'sent_at' => Iso::format($invitation->sent_at),
            'viewed_at' => Iso::format($invitation->viewed_at),
            'joined_at' => Iso::format($invitation->joined_at),
            'declined_at' => Iso::format($invitation->declined_at),
            'decline_reason' => $invitation->decline_reason,
            'revoked_at' => Iso::format($invitation->revoked_at),
            'revoke_reason' => $invitation->revoke_reason?->value,
            'expired_at' => Iso::format($invitation->expired_at),
            'created_at' => Iso::format($invitation->created_at),
        ];
    }

    /**
     * @param  Collection<int, Invitation>  $invitations
     * @return array{0: array<int, Coverage>, 1: array<int, string>}
     */
    private function billing(Collection $invitations): array
    {
        if ($invitations->isEmpty()) {
            return [[], []];
        }

        (new EloquentCollection($invitations->all()))->loadMissing(['organization.logoFile', 'vendor.externalRefs', 'participant', 'competition']);

        $coverage = $this->access->coverageFor($invitations);

        /** @var array<int, string> $passes */
        $passes = SponsoredPass::query()
            ->whereIn('invitation_id', $invitations->pluck('id')->all())
            ->orderBy('id')
            ->get(['invitation_id', 'status'])
            ->mapWithKeys(static fn (SponsoredPass $pass): array => [$pass->invitation_id => $pass->status->value])
            ->all();

        return [$coverage, $passes];
    }
}
