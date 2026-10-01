<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\ApiClients;

use App\Modules\Integrations\Models\ApiKey;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * `DELETE /integrations/api-clients/{client}/keys/{key}`: sets `revoked_at` (idempotent).
 */
final class RevokeApiKey
{
    public function handle(ApiKey $key, Actor $actor): ApiKey
    {
        return DB::transaction(static function () use ($key, $actor): ApiKey {
            $key = ApiKey::query()->with('apiClient')->lockForUpdate()->findOrFail($key->id);

            if ($key->revoked_at !== null) {
                return $key;
            }

            $key->forceFill(['revoked_at' => Date::now()])->save();

            AuditLogger::log('api_key.revoked', $key->apiClient, meta: ['key_id' => $key->public_id, 'prefix' => $key->prefix], actor: $actor);

            return $key;
        });
    }
}
