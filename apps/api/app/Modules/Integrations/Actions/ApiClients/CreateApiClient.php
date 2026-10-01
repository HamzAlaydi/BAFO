<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\ApiClients;

use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Data\IssuedApiClient;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

/**
 * `POST /integrations/api-clients` (ARCHITECTURE §14.1): the `api_clients` row plus a Passport
 * client-credentials client whose id is stored in `oauth_client_id`. The plain `client_secret` is
 * returned once.
 */
final readonly class CreateApiClient
{
    public function __construct(private ClientRepository $passportClients) {}

    /**
     * @param  array{name: string, description: string|null, scopes: list<string>}  $data
     */
    public function handle(Organization $organization, array $data, Actor $actor): IssuedApiClient
    {
        return DB::transaction(function () use ($organization, $data, $actor): IssuedApiClient {
            $passportClient = $this->passportClients->createClientCredentialsGrantClient($data['name']);

            $client = ApiClient::query()->create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'scopes' => $data['scopes'],
                'status' => ApiClientStatus::Active,
                'oauth_client_id' => $passportClient->getKey(),
                'created_by_user_id' => $actor->userId,
            ]);

            AuditLogger::log('api_client.created', $client, meta: ['scopes' => $data['scopes']], actor: $actor);

            return new IssuedApiClient($client, (string) $passportClient->plainSecret);
        });
    }
}
