<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Listeners;

use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

/**
 * `Identity\EmailVerified` (ARCHITECTURE §10, queue `default`): vendors of other issuers whose
 * e-mail is the verified user's (or the organization's contact e-mail), or whose CR is the
 * organization's, get `linked_organization_id`. Vendors already linked are left alone.
 */
final class LinkVendorsToOrganization implements ShouldHandleEventsAfterCommit, ShouldQueueAfterCommit
{
    public string $queue = 'default';

    public function handle(object $event): void
    {
        $user = EventProperty::get($event, 'user', User::class);
        $organization = Organization::query()->whereKey(
            $user->membership()->value('organization_id'),
        )->first();

        if ($organization === null) {
            return;
        }

        $emails = array_values(array_unique([mb_strtolower($user->email), mb_strtolower($organization->email)]));

        Vendor::query()
            ->whereNull('linked_organization_id')
            ->where('organization_id', '!=', $organization->id)
            ->where(static function ($query) use ($emails, $organization): void {
                $query->whereIn('email', $emails)->orWhere('cr_number', $organization->cr_number);
            })
            ->update(['linked_organization_id' => $organization->id, 'updated_at' => now()]);
    }
}
