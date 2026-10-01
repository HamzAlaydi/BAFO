<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Database\Factories;

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Database\Factories\Support\SaudiData;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A draft invitation of an e-mail with no known organization. Status states follow §6.2; the
 * token is stored as its sha256 (pass the plain token to `sent()` to use it in a test).
 *
 * @extends Factory<Invitation>
 */
final class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => Competition::factory(),
            'email' => 'procurement'.$this->faker->unique()->numerify('#####').'@'.$this->faker->randomElement(['nokhba.sa', 'madar.com.sa', 'waha.sa']),
            'name' => SaudiData::personName(),
            'organization_id' => null,
            'vendor_id' => null,
            'status' => InvitationStatus::Draft,
            'sponsored_requested' => false,
            'token_hash' => null,
            'invited_by_user_id' => null,
            'invited_by_api_client_id' => null,
        ];
    }

    /**
     * Addressed to a known organization (its e-mail).
     */
    public function forOrganization(Organization $organization): self
    {
        return $this->state(['organization_id' => $organization->id, 'email' => $organization->email]);
    }

    public function forVendor(Vendor $vendor): self
    {
        return $this->state(['vendor_id' => $vendor->id, 'email' => $vendor->email, 'name' => $vendor->contact_name]);
    }

    public function sent(?string $plainToken = null): self
    {
        return $this->state(fn (): array => [
            'status' => InvitationStatus::Sent,
            'token_hash' => hash('sha256', $plainToken ?? Str::random(48)),
            'sent_at' => now()->subHour(),
        ]);
    }

    public function viewed(): self
    {
        return $this->sent()->state(fn (): array => [
            'status' => InvitationStatus::Viewed,
            'viewed_at' => now()->subMinutes(30),
        ]);
    }

    public function joined(): self
    {
        return $this->viewed()->state(fn (): array => [
            'status' => InvitationStatus::Joined,
            'joined_at' => now()->subMinutes(20),
        ]);
    }

    public function declined(): self
    {
        return $this->sent()->state(fn (): array => [
            'status' => InvitationStatus::Declined,
            'declined_at' => now()->subMinutes(10),
            'decline_reason' => 'لا تتوفر لدينا الكميات المطلوبة حالياً',
        ]);
    }

    public function revoked(RevokeReason $reason = RevokeReason::Issuer): self
    {
        return $this->sent()->state(fn (): array => [
            'status' => InvitationStatus::Revoked,
            'revoked_at' => now()->subMinutes(10),
            'revoke_reason' => $reason,
        ]);
    }

    public function expired(): self
    {
        return $this->sent()->state(fn (): array => [
            'status' => InvitationStatus::Expired,
            'expired_at' => now()->subMinutes(5),
        ]);
    }

    public function sponsoredRequested(): self
    {
        return $this->state(['sponsored_requested' => true]);
    }
}
