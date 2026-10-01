<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\ApiClients;

use App\Modules\Integrations\Models\ApiClient;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\DB;

/**
 * `PATCH /integrations/api-clients/{client}`: name, description and scopes. Narrowed scopes
 * apply at once to tokens already issued (the middleware intersects them with the client's).
 */
final class UpdateApiClient
{
    /**
     * @param  array{name?: string, description?: string|null, scopes?: list<string>}  $data
     */
    public function handle(ApiClient $client, array $data, Actor $actor): ApiClient
    {
        return DB::transaction(static function () use ($client, $data, $actor): ApiClient {
            $client->fill($data)->save();

            $changes = AuditLogger::diff($client, ['name', 'description', 'scopes']);

            if ($changes !== []) {
                AuditLogger::log('api_client.updated', $client, $changes, actor: $actor);
            }

            return $client;
        });
    }
}
