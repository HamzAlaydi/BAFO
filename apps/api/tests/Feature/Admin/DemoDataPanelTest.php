<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\Competitions\CompetitionResource;
use App\Modules\Admin\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Modules\Admin\Filament\Resources\Organizations\OrganizationResource;
use App\Modules\Admin\Filament\Resources\Organizations\Pages\ViewOrganization;
use App\Modules\Admin\Filament\Widgets\LiveCompetitionsTable;
use App\Modules\Admin\Filament\Widgets\PlatformStatsOverview;
use App\Modules\Admin\Models\Admin;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Payment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Audit\AuditLog;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * The panel on the §17 demo data: the seeded super admin opens every §16 page and every record
 * page of the demo scenario (competitions in every state, subscriptions, payments, invoices,
 * the sponsored tender, the API client).
 */

it('renders every page and record of the demo scenario', function () {
    Storage::fake('private');
    Storage::fake('public');
    Mail::fake();
    Notification::fake();

    $this->seed(DemoSeeder::class);

    $admin = Admin::query()->where('email', DemoSeeder::ADMIN_EMAIL)->sole();
    $this->actingAs($admin, 'admin');

    foreach ([...AdminPanel::INDEXES, ...AdminPanel::SUPER_ADMIN_ONLY] as $url) {
        $this->get($url)->assertOk();
    }

    $records = [
        'organizations' => Organization::query()->pluck('public_id'),
        'users' => User::query()->pluck('public_id'),
        'competitions' => Competition::query()->pluck('public_id'),
        'payments' => Payment::query()->pluck('public_id'),
        'invoices' => Invoice::query()->pluck('public_id'),
        'api-clients' => ApiClient::query()->pluck('public_id'),
        'webhook-endpoints' => WebhookEndpoint::query()->pluck('public_id'),
        'legal-documents' => LegalDocument::query()->limit(3)->pluck('public_id'),
        'audit-log' => AuditLog::query()->latest('id')->limit(5)->pluck('id'),
    ];

    expect($records['competitions'])->toHaveCount(12);

    foreach ($records as $resource => $ids) {
        foreach ($ids as $id) {
            $this->get("/admin/{$resource}/{$id}")->assertOk();
        }
    }

    $this->get('/admin/sponsorships')->assertOk()->assertSee('BAFO-T-');

    // Relation managers load lazily on the record pages: render each on the demo records.
    AdminPanel::signIn($admin);

    foreach (Competition::query()->get() as $competition) {
        foreach (CompetitionResource::getRelations() as $manager) {
            Livewire::test($manager, ['ownerRecord' => $competition, 'pageClass' => ViewCompetition::class])->assertOk();
        }
    }

    foreach (Organization::query()->get() as $organization) {
        foreach (OrganizationResource::getRelations() as $manager) {
            Livewire::test($manager, ['ownerRecord' => $organization, 'pageClass' => ViewOrganization::class])->assertOk();
        }
    }

    Livewire::test(PlatformStatsOverview::class)->assertOk();
    Livewire::test(LiveCompetitionsTable::class)->assertCanSeeTableRecords(Competition::query()->where('status', 'live')->get());
});
