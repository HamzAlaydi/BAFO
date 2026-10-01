<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\ApiClients;

use App\Modules\Integrations\Data\IssuedApiClient;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\ClientRepository;

/**
 * `POST /integrations/api-clients/{client}/rotate-secret` (ARCHITECTURE §14.1): Passport
 * regenerates the secret; the plain value is returned once. Access tokens already issued stay
 * valid until they expire (30 min). A revoked client cannot be rotated (409).
 */
final readonly class RotateApiClientSecret
{
    public function __construct(private ClientRepository $passportClients) {}

    public function handle(ApiClient $client, Actor $actor): IssuedApiClient
    {
        return DB::transaction(function () use ($client, $actor): IssuedApiClient {
            $client = ApiClient::query()->lockForUpdate()->findOrFail($client->id);

            if ($client->status === ApiClientStatus::Revoked) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => $client->status->value]);
            }

            $passportClient = $client->oauth_client_id !== null
                ? PassportClient::query()->find($client->oauth_client_id)
                : null;

            if ($passportClient === null) {
                // A client created outside CreateApiClient (seed, factory) gets its Passport client now.
                $passportClient = $this->passportClients->createClientCredentialsGrantClient($client->name);
                $client->forceFill(['oauth_client_id' => $passportClient->getKey()])->save();
            } else {
                $this->passportClients->regenerateSecret($passportClient);
            }

            AuditLogger::log('api_client.secret_rotated', $client, actor: $actor);

            return new IssuedApiClient($client, (string) $passportClient->plainSecret);
        });
    }
}
