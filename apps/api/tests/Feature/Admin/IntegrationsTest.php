<?php

declare(strict_types=1);

use App\Modules\Admin\Filament\Resources\ApiClients\Pages\ListApiClients;
use App\Modules\Admin\Filament\Resources\ApiClients\Pages\ViewApiClient;
use App\Modules\Admin\Filament\Resources\WebhookEndpoints\Pages\ListWebhookEndpoints;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Models\ApiKey;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Support\Audit\AuditLog;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Tests\Support\Admin\AdminPanel;

/*
 * §16 API clients and webhook endpoints: read-only per organization; suspend / reactivate / revoke
 * a client through the Integrations Actions.
 */

beforeEach(function () {
    $this->admin = AdminPanel::signIn(AdminPanel::operator());
});

it('shows API clients with masked keys only', function () {
    $client = ApiClient::factory()->create(['name' => 'SAP Connector']);
    $key = ApiKey::factory()->create(['api_client_id' => $client->id]);

    Livewire::test(ListApiClients::class)->assertCanSeeTableRecords([$client]);

    $this->get('/admin/api-clients/'.$client->public_id)
        ->assertOk()
        ->assertSee('SAP Connector')
        ->assertSee($key->prefix.'…'.$key->last_four)
        ->assertDontSee($key->key_hash);
});

it('suspends and reactivates a client through SetApiClientSuspension', function () {
    $client = ApiClient::factory()->create();

    Livewire::test(ListApiClients::class)
        ->assertActionHidden(TestAction::make('reactivate')->table($client))
        ->callAction(TestAction::make('suspend')->table($client));

    expect($client->refresh()->status)->toBe(ApiClientStatus::Suspended)
        ->and(AuditLog::query()->where('action', 'api_client.suspended')->value('actor_id'))->toBe($this->admin->id);

    Livewire::test(ViewApiClient::class, ['record' => $client->public_id])->callAction('reactivate');

    expect($client->refresh()->status)->toBe(ApiClientStatus::Active);
});

it('revokes a client through RevokeApiClient', function () {
    $client = ApiClient::factory()->create();

    Livewire::test(ViewApiClient::class, ['record' => $client->public_id])->callAction('revoke');

    expect($client->refresh()->status)->toBe(ApiClientStatus::Revoked);

    Livewire::test(ViewApiClient::class, ['record' => $client->public_id])
        ->assertActionHidden('revoke')
        ->assertActionHidden('suspend')
        ->assertActionHidden('reactivate');
});

it('lists webhook endpoints without their signing secret', function () {
    $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://erp.example.com/hooks/bafo']);

    Livewire::test(ListWebhookEndpoints::class)->assertCanSeeTableRecords([$endpoint]);

    $this->get('/admin/webhook-endpoints/'.$endpoint->public_id)
        ->assertOk()
        ->assertSee('https://erp.example.com/hooks/bafo')
        ->assertDontSee($endpoint->secret);
});
