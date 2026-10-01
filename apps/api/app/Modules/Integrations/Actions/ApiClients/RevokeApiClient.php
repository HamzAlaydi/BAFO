<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\ApiClients;

use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\Token;

/**
 * `DELETE /integrations/api-clients/{client}` (ARCHITECTURE §14.1): the client can no longer
 * authenticate by either method. Its Passport tokens are revoked, the Passport client is flagged
 * `revoked` (never through the deprecated ClientRepository::delete()), and every key gets
 * `revoked_at`. Revoking twice is a no-op.
 */
final class RevokeApiClient
{
    public function handle(ApiClient $client, Actor $actor): ApiClient
    {
        return DB::transaction(static function () use ($client, $actor): ApiClient {
            $client = ApiClient::query()->lockForUpdate()->findOrFail($client->id);

            if ($client->status === ApiClientStatus::Revoked) {
                return $client;
            }

            $now = Date::now();

            $passportClient = $client->oauth_client_id !== null
                ? PassportClient::query()->find($client->oauth_client_id)
                : null;

            if ($passportClient !== null) {
                $passportClient->tokens()->each(static fn (Token $token): bool => $token->revoke());
                $passportClient->forceFill(['revoked' => true])->save();
            }

            $client->keys()->whereNull('revoked_at')->update(['revoked_at' => $now, 'updated_at' => $now]);

            $client->forceFill(['status' => ApiClientStatus::Revoked, 'revoked_at' => $now])->save();

            AuditLogger::log('api_client.revoked', $client, actor: $actor);

            return $client;
        });
    }
}
