<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Actions;

use App\Modules\Billing\Contracts\SponsorshipService;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Services\CompetitionSettings;
use App\Modules\Competitions\Services\InvitationSender;
use App\Modules\Competitions\Services\RulesValidator;
use App\Modules\Competitions\Services\StateMachine;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Invites participants (ARCHITECTURE §13.11 "Create", API.md §1.4). All or nothing.
 *
 * Each row is an e-mail, an organization (from suggestions) or a vendor of the issuer, plus
 * `name` and `sponsored`. Per-row problems are 422 `validation_failed` with
 * `errors."invitations.{i}.<field>"` and `details.item_codes` (`invitation_duplicate`,
 * `cannot_invite_own_organization`, `vendor_blocked`, `vendor_not_found`). On a draft the
 * invitations are drafts; on a scheduled or live competition they are sent at once (sponsored
 * rows reserve their passes first).
 *
 * Also called by Billing after a sponsorship payment (`intent: invite`, all rows sponsored).
 */
final readonly class InviteParticipants
{
    /**
     * Invitation statuses that still occupy a participant place, or already hold the organization.
     *
     * CONTRACT-GAP: "already invited" (§13.11) means the same e-mail in any status (the unique
     * index), or the same organization with an invitation in one of these statuses: a declined,
     * expired or revoked organization can be invited again under another e-mail.
     *
     * @var list<string>
     */
    private const array ACTIVE_STATUSES = ['draft', 'sent', 'viewed', 'joined'];

    public function __construct(
        private SponsorshipService $sponsorship,
        private InvitationSender $sender,
        private CompetitionSettings $settings,
    ) {}

    /**
     * @param  list<array{email?: string|null, name?: string|null, organization_id?: int|null, vendor_id?: int|null, sponsored?: bool}>  $rows
     * @return Collection<int, Invitation>
     */
    public function handle(Competition $c, array $rows, Actor $actor): Collection
    {
        return DB::transaction(function () use ($c, $rows, $actor): Collection {
            $competition = Competition::query()->with('organization')->whereKey($c->id)->lockForUpdate()->firstOrFail();
            $published = $competition->status !== CompetitionStatus::Draft;

            if (! in_array($competition->status, [CompetitionStatus::Draft, CompetitionStatus::Scheduled, CompetitionStatus::Live], true)) {
                throw StateMachine::invalid($competition->status);
            }

            if ($published && ($competition->invitation_cutoff_at === null || Date::now()->greaterThanOrEqualTo($competition->invitation_cutoff_at))) {
                throw new ApiException(
                    errorCode: 'invitation_cutoff_passed',
                    messageKey: 'competitions.errors.invitation_cutoff_passed',
                    status: 409,
                );
            }

            $resolved = $this->resolve($competition, $rows);

            $occupied = Invitation::query()
                ->where('competition_id', $competition->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->count();
            $max = $this->settings->maxParticipants();

            if ($occupied + count($resolved) > $max) {
                throw RulesValidator::maxParticipantsExceeded($max);
            }

            $invitations = new Collection;

            foreach ($resolved as $row) {
                $invitation = new Invitation;
                $invitation->forceFill([
                    'competition_id' => $competition->id,
                    'email' => $row['email'],
                    'name' => $row['name'],
                    'organization_id' => $row['organization_id'],
                    'vendor_id' => $row['vendor_id'],
                    'status' => InvitationStatus::Draft,
                    'sponsored_requested' => $row['sponsored'],
                    'invited_by_user_id' => $actor->userId,
                    'invited_by_api_client_id' => $actor->apiClientId,
                ])->save();

                $invitations->push($invitation);
            }

            if ($published) {
                $this->sponsorship->reserveForInvitations($competition, $invitations);
                $this->sender->sendAll($competition, $invitations, $actor);

                event(new CompetitionUpdated($competition, ['invitations'], $actor));
            } else {
                foreach ($invitations as $invitation) {
                    AuditLogger::log('invitation.created', $invitation, meta: ['competition_id' => $competition->public_id],
                        actor: $actor, organizationId: $competition->organization_id);
                }
            }

            return $invitations;
        });
    }

    /**
     * Resolves every row to an e-mail and, when known, an organization; collects the per-row
     * problems.
     *
     * @param  list<array{email?: string|null, name?: string|null, organization_id?: int|null, vendor_id?: int|null, sponsored?: bool}>  $rows
     * @return list<array{email: string, name: string|null, organization_id: int|null, vendor_id: int|null, sponsored: bool}>
     */
    private function resolve(Competition $competition, array $rows): array
    {
        $issuer = $competition->organization;
        $existing = Invitation::query()
            ->where('competition_id', $competition->id)
            ->get(['email', 'organization_id', 'status']);
        $takenEmails = $existing->pluck('email')->all();
        $takenOrganizations = $existing
            ->filter(static fn (Invitation $i): bool => in_array($i->status->value, self::ACTIVE_STATUSES, true))
            ->pluck('organization_id')->filter()->all();

        $resolved = [];
        $errors = [];
        $codes = [];

        foreach ($rows as $index => $row) {
            $field = isset($row['vendor_id']) ? 'vendor_id' : (isset($row['organization_id']) ? 'organization_id' : 'email');
            $path = "invitations.{$index}.{$field}";
            $name = isset($row['name']) && trim($row['name']) !== '' ? trim($row['name']) : null;
            $organizationId = null;
            $vendorId = null;
            $crNumber = null;

            if (isset($row['vendor_id'])) {
                $vendor = Vendor::query()->whereKey($row['vendor_id'])->where('organization_id', $issuer->id)->first();

                if ($vendor === null) {
                    $this->fail($errors, $codes, $path, 'vendor_not_found');

                    continue;
                }

                if ($vendor->status === VendorStatus::Blocked) {
                    $this->fail($errors, $codes, $path, 'vendor_blocked');

                    continue;
                }

                $email = $vendor->email;
                $vendorId = $vendor->id;
                $crNumber = $vendor->cr_number;
                $name ??= $vendor->contact_name;
                // SECURITY_REVIEW S-12: the vendor's link (matched on e-mails and CR numbers an
                // organization may simply claim) binds the invitation only on a proven identity;
                // otherwise the invitation stays open for the address owner to claim.
                $linked = $vendor->linked_organization_id !== null ? Organization::query()->find($vendor->linked_organization_id) : null;
                $organizationId = $linked?->isProvenRecipient((string) $email, $crNumber) === true ? $linked->id : null;
            } elseif (isset($row['organization_id'])) {
                $organization = Organization::query()->find($row['organization_id']);

                if ($organization === null) {
                    $this->fail($errors, $codes, $path, 'organization_not_found');

                    continue;
                }

                $email = $organization->email;
                $organizationId = $organization->id;
            } else {
                $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
                // SECURITY_REVIEW S-12: only a verified, active member's address binds the
                // invitation (not a pending team member another organization created).
                $organizationId = User::provenOrganizationIdFor($email);
            }

            $email = mb_strtolower(trim($email));

            if ($organizationId !== null && $crNumber === null) {
                $crNumber = Organization::query()->whereKey($organizationId)->value('cr_number');
            }

            if ($organizationId === $issuer->id || ($crNumber !== null && $crNumber === $issuer->cr_number)) {
                $this->fail($errors, $codes, $path, 'cannot_invite_own_organization');

                continue;
            }

            if (in_array($email, $takenEmails, true) || ($organizationId !== null && in_array($organizationId, $takenOrganizations, true))) {
                $this->fail($errors, $codes, $path, 'invitation_duplicate');

                continue;
            }

            $takenEmails[] = $email;

            if ($organizationId !== null) {
                $takenOrganizations[] = $organizationId;
            }

            $resolved[] = [
                'email' => $email,
                'name' => $name,
                'organization_id' => $organizationId,
                'vendor_id' => $vendorId,
                'sponsored' => (bool) ($row['sponsored'] ?? false),
            ];
        }

        if ($errors !== []) {
            throw new ApiException(
                errorCode: 'validation_failed',
                messageKey: 'errors.validation_failed',
                status: 422,
                errors: $errors,
                details: ['item_codes' => $codes],
            );
        }

        return $resolved;
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string>  $codes
     */
    private function fail(array &$errors, array &$codes, string $path, string $code): void
    {
        $message = __('competitions.validation.'.$code);
        $errors[$path] = [is_string($message) ? $message : $code];

        // An organization that disappeared since the request was validated is a plain field error.
        if ($code !== 'organization_not_found') {
            $codes[$path] = $code;
        }
    }
}
