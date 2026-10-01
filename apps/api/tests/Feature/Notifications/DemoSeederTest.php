<?php

declare(strict_types=1);

use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Database\Seeders\NotificationsDemoSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Tests\Support\Notifications\NotificationScenario as S;
use Tests\Support\Notifications\RecordingPushNotifier;

/*
 * NotificationsDemoSeeder (docs/build/DEMO.md): a few in-app notifications per demo user,
 * built from the seeded demo data, in-app only.
 */

it('gives the demo users in-app notifications about their real demo data, newest unread', function () {
    $push = RecordingPushNotifier::swap();
    $issuerOrganization = Organization::factory()->create();
    $issuer = User::factory()->withMembership($issuerOrganization, OrgRole::Owner)->create(['email' => DemoSeeder::user('issuer.owner')['email']]);
    $supplierOrganization = Organization::factory()->create();
    $supplier = User::factory()->withMembership($supplierOrganization, OrgRole::Owner)->create(['email' => DemoSeeder::user('supplier_a.owner')['email']]);
    $live = S::competition($issuerOrganization);
    S::participant($live, organization: $supplierOrganization);
    $scheduled = S::competition($issuerOrganization, fn ($f) => $f->scheduled());
    Invitation::factory()->forOrganization($supplierOrganization)->sent()->create(['competition_id' => $scheduled->id]);

    $this->seed(NotificationsDemoSeeder::class);

    $issuerTypes = $issuer->notifications()->get()->map(fn (DatabaseNotification $n): string => $n->getAttribute('data')['type'])->all();
    $supplierTypes = $supplier->notifications()->get()->map(fn (DatabaseNotification $n): string => $n->getAttribute('data')['type'])->all();

    expect($issuerTypes)->toContain('offer.received', 'invitation.joined')
        ->and($supplierTypes)->toContain('competition.invited', 'competition.opened')
        ->and($issuer->unreadNotifications()->count())->toBe(1)
        ->and($supplier->unreadNotifications()->count())->toBe(1)
        ->and($push->sent)->toBe([])
        ->and(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(0);
});

it('does nothing when the demo users do not exist', function () {
    $this->seed(NotificationsDemoSeeder::class);

    expect(DatabaseNotification::query()->count())->toBe(0);
});
