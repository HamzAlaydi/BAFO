<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Exceptions\ApiException;

/**
 * Validates and resolves the rows of a sponsorship checkout with `intent: invite` up front,
 * "exactly like POST …/invitations" (API.md §1.7, ARCHITECTURE §13.11):
 *
 * - `organization_id` → the organization's e-mail; `vendor_id` → the vendor's e-mail and linked
 *   organization; a raw e-mail → the organization of the user with that e-mail;
 * - per-row item codes: `invitation_duplicate`, `cannot_invite_own_organization`,
 *   `vendor_blocked`, `vendor_not_found` (422 `validation_failed`, all-or-nothing).
 *
 * CONTRACT-GAP: §3.6 gives Billing no Competitions contract for this check, so the rules are
 * mirrored here; `InviteParticipants` re-checks every row after the payment.
 */
final class InvitationRowResolver
{
    /**
     * @param  list<array{email?: string|null, organization_id?: string|null, vendor_id?: string|null, name?: string|null}>  $rows
     * @return list<array{email: string, name: string|null, organization_id: int|null, vendor_id: int|null, sponsored: bool}>
     *
     * @throws ApiException validation_failed (422, `details.item_codes`)
     */
    public function resolve(Competition $competition, array $rows): array
    {
        $issuer = $competition->organization;
        $existing = $competition->invitations()
            ->where('status', '!=', InvitationStatus::Revoked->value)
            ->get(['email', 'organization_id']);
        $takenEmails = $existing->pluck('email')->map(static fn (mixed $e): string => mb_strtolower((string) $e))->flip()->all();
        $takenOrganizations = $existing->pluck('organization_id')->filter()->map(static fn (mixed $id): int => (int) $id)->flip()->all();

        $resolved = [];
        $errors = [];
        $codes = [];

        foreach ($rows as $i => $row) {
            [$field, $email, $organizationId, $vendorId, $code] = $this->resolveRow($issuer, $row);

            if ($code === null && $email !== null) {
                $code = match (true) {
                    isset($takenEmails[$email]) || ($organizationId !== null && isset($takenOrganizations[$organizationId])) => 'invitation_duplicate',
                    $organizationId !== null && $this->isOwnOrganization($issuer, $organizationId) => 'cannot_invite_own_organization',
                    default => null,
                };
            }

            if ($code !== null || $email === null) {
                $path = "invitations.{$i}.{$field}";
                $errors[$path] = [$this->message('billing.validation.item_codes.'.($code ?? 'not_found'))];

                if ($code !== null) {
                    $codes[$path] = $code;
                }

                continue;
            }

            $takenEmails[$email] = true;

            if ($organizationId !== null) {
                $takenOrganizations[$organizationId] = true;
            }

            $name = isset($row['name']) && trim((string) $row['name']) !== '' ? trim((string) $row['name']) : null;
            $resolved[] = ['email' => $email, 'name' => $name, 'organization_id' => $organizationId, 'vendor_id' => $vendorId, 'sponsored' => true];
        }

        if ($errors !== []) {
            throw new ApiException(
                'validation_failed',
                'errors.validation_failed',
                422,
                errors: $errors,
                details: $codes === [] ? [] : ['item_codes' => $codes],
            );
        }

        return $resolved;
    }

    /**
     * Unsaved invitations for the quote (all sponsored).
     *
     * @param  list<array{email: string, name: string|null, organization_id: int|null, vendor_id: int|null, sponsored: bool}>  $rows
     * @return list<Invitation>
     */
    public function candidates(Competition $competition, array $rows): array
    {
        return array_map(static function (array $row) use ($competition): Invitation {
            $invitation = new Invitation;
            $invitation->forceFill([
                'competition_id' => $competition->id,
                'email' => $row['email'],
                'name' => $row['name'],
                'organization_id' => $row['organization_id'],
                'vendor_id' => $row['vendor_id'],
                'status' => InvitationStatus::Draft,
                'sponsored_requested' => true,
            ]);
            $invitation->setRelation('competition', $competition);

            return $invitation;
        }, $rows);
    }

    /**
     * @param  array{email?: string|null, organization_id?: string|null, vendor_id?: string|null, name?: string|null}  $row
     * @return array{0: string, 1: string|null, 2: int|null, 3: int|null, 4: string|null} field, e-mail, organization, vendor, item code
     */
    private function resolveRow(Organization $issuer, array $row): array
    {
        if (! empty($row['vendor_id'])) {
            $vendor = Vendor::query()
                ->where('organization_id', $issuer->id)
                ->wherePublicId(strtolower((string) $row['vendor_id']))
                ->first();

            return match (true) {
                $vendor === null => ['vendor_id', null, null, null, 'vendor_not_found'],
                $vendor->status === VendorStatus::Blocked => ['vendor_id', null, null, null, 'vendor_blocked'],
                default => ['vendor_id', mb_strtolower($vendor->email), self::provenLink($vendor), $vendor->id, null],
            };
        }

        if (! empty($row['organization_id'])) {
            $organization = Organization::query()
                ->wherePublicId(strtolower((string) $row['organization_id']))
                ->where('status', OrganizationStatus::Active->value)
                ->first();

            return $organization === null
                ? ['organization_id', null, null, null, null]
                : ['organization_id', mb_strtolower($organization->email), $organization->id, null, null];
        }

        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));

        // Mirrors InviteParticipants (SECURITY_REVIEW S-12): only a proven address binds.
        return ['email', $email, User::provenOrganizationIdFor($email), null, null];
    }

    /**
     * The vendor's linked organization when it is a proven recipient of the vendor's address
     * (SECURITY_REVIEW S-12, as InviteParticipants binds it), else null.
     */
    private static function provenLink(Vendor $vendor): ?int
    {
        $linked = $vendor->linked_organization_id !== null ? Organization::query()->find($vendor->linked_organization_id) : null;

        return $linked?->isProvenRecipient((string) $vendor->email, $vendor->cr_number) === true ? $linked->id : null;
    }

    private function isOwnOrganization(Organization $issuer, int $organizationId): bool
    {
        if ($organizationId === $issuer->id) {
            return true;
        }

        return Organization::query()->whereKey($organizationId)->where('cr_number', $issuer->cr_number)->exists();
    }

    private function message(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
