<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\ApiClients;

use App\Modules\Integrations\Data\IssuedApiKey;
use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Modules\Integrations\Services\ApiKeyGenerator;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `POST /integrations/api-clients/{client}/keys` (ARCHITECTURE §14.1): a key valid for
 * `expires_in_days` (1–730, default 365). The plain key is returned once.
 */
final readonly class CreateApiKey
{
    public function __construct(private ApiKeyGenerator $generator) {}

    public function handle(ApiClient $client, int $expiresInDays, Actor $actor): IssuedApiKey
    {
        return DB::transaction(function () use ($client, $expiresInDays, $actor): IssuedApiKey {
            $client = ApiClient::query()->lockForUpdate()->findOrFail($client->id);

            if ($client->status === ApiClientStatus::Revoked) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['status' => $client->status->value]);
            }

            $plainKey = $this->generator->generate();

            $key = $client->keys()->create([
                'prefix' => explode('_', $plainKey)[2],
                'key_hash' => hash('sha256', $plainKey),
                'last_four' => substr($plainKey, -4),
                'expires_at' => Date::now()->addDays($expiresInDays),
                'created_by_user_id' => $actor->userId,
            ]);

            AuditLogger::log('api_key.created', $client, meta: ['key_id' => $key->public_id, 'prefix' => $key->prefix], actor: $actor);

            return new IssuedApiKey($key, $plainKey);
        });
    }
}
