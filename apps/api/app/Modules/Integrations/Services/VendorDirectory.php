<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services;

use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\Vendor;

/**
 * Lookups over an issuer's vendor directory used by webhooks, exports and the public API.
 */
final class VendorDirectory
{
    /**
     * The issuer's vendor entry for a counterparty: the vendor the invitation was sent from, else
     * a (non-archived first) vendor of the issuer linked to the counterparty's organization.
     */
    public function forCounterparty(int $issuerOrganizationId, ?int $vendorId, ?int $organizationId): ?Vendor
    {
        if ($vendorId !== null) {
            $vendor = Vendor::query()->with('externalRefs')
                ->where('organization_id', $issuerOrganizationId)
                ->whereKey($vendorId)
                ->first();

            if ($vendor !== null) {
                return $vendor;
            }
        }

        if ($organizationId === null) {
            return null;
        }

        return Vendor::query()->with('externalRefs')
            ->where('organization_id', $issuerOrganizationId)
            ->where('linked_organization_id', $organizationId)
            ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [VendorStatus::Archived->value])
            ->orderBy('id')
            ->first();
    }

    /**
     * `{"id", "external_refs"}` (API.md §4.1 `vendor`), or null.
     *
     * @return array{id: string, external_refs: list<array{system: string, type: string, id: string, number: string|null, url: string|null}>}|null
     */
    public static function summary(?Vendor $vendor): ?array
    {
        if ($vendor === null) {
            return null;
        }

        return ['id' => $vendor->public_id, 'external_refs' => ExternalRefs::present($vendor->externalRefs)];
    }

    /**
     * The BAFO organization a vendor stands for, matched by e-mail (the organization's contact
     * e-mail or a member's e-mail) or by CR number. The owning issuer never links to itself.
     *
     * CONTRACT-GAP: §5.4 says "the e-mail or CR matches a BAFO organization"; a member's e-mail
     * counts too, because invitations go to people.
     */
    public function matchOrganization(int $ownerOrganizationId, string $email, ?string $crNumber): ?Organization
    {
        $email = mb_strtolower(trim($email));

        $byOrganization = Organization::query()
            ->where('id', '!=', $ownerOrganizationId)
            ->where(static function ($query) use ($email, $crNumber): void {
                $query->where('email', $email);

                if ($crNumber !== null && $crNumber !== '') {
                    $query->orWhere('cr_number', $crNumber);
                }
            })
            ->orderBy('id')
            ->first();

        if ($byOrganization !== null) {
            return $byOrganization;
        }

        $organizationId = Membership::query()
            ->whereIn('user_id', User::query()->select('id')->where('email', $email))
            ->where('organization_id', '!=', $ownerOrganizationId)
            ->value('organization_id');

        return is_numeric($organizationId) ? Organization::query()->find((int) $organizationId) : null;
    }
}
