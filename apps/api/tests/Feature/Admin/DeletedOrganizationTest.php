<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\AwardsRelationManager;
use App\Modules\Admin\Filament\Resources\Competitions\RelationManagers\ParticipantsRelationManager;
use App\Modules\Bidding\Models\Award;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\ApiClient;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * Account deletion soft-deletes an organization but keeps its payments, invoices, subscriptions,
 * competitions and participations (§13.8): the panel still shows them, with the organization's name.
 */

it('keeps the records of a deleted organization visible', function () {
    Storage::fake('private');
    $admin = AdminPanel::superAdmin();
    $organization = Organization::factory()->create(['name' => 'Gone Trading Co']);
    $payment = Payment::factory()->succeeded()->create(['organization_id' => $organization->id]);
    $invoice = Invoice::factory()->create(['payment_id' => $payment->id]);
    Subscription::factory()->create(['organization_id' => $organization->id]);
    $competition = Competition::factory()->awarded()->create(['organization_id' => $organization->id]);
    $client = ApiClient::factory()->create(['organization_id' => $organization->id]);
    $participant = Participant::factory()->create(['organization_id' => $organization->id]);
    $award = Award::factory()->create(['organization_id' => $organization->id, 'competition_id' => $participant->competition_id, 'participant_id' => $participant->id]);

    $organization->forceFill(['status' => OrganizationStatus::Deleted])->save();
    $organization->delete();

    $this->actingAs($admin, 'admin');

    foreach ([
        '/admin/organizations/'.$organization->public_id,
        '/admin/payments',
        '/admin/payments/'.$payment->public_id,
        '/admin/invoices/'.$invoice->public_id,
        '/admin/subscriptions',
        '/admin/competitions/'.$competition->public_id,
        '/admin/api-clients/'.$client->public_id,
    ] as $url) {
        $this->get($url)->assertOk()->assertSee('Gone Trading Co');
    }

    AdminPanel::signIn($admin);
    $context = ['ownerRecord' => $participant->competition, 'pageClass' => ViewCompetition::class];

    Livewire::test(ParticipantsRelationManager::class, $context)->assertSee('Gone Trading Co');
    Livewire::test(AwardsRelationManager::class, $context)->assertCanSeeTableRecords([$award])->assertSee('Gone Trading Co');
});
