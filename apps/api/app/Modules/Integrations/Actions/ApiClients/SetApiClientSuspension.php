<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions\ApiClients;

use App\Modules\Integrations\Enums\ApiClientStatus;
use App\Modules\Integrations\Models\ApiClient;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;

/**
 * Platform admin "Suspend client" / "Reactivate client" (ARCHITECTURE §16): `active ↔ suspended`.
 * A suspended client cannot authenticate or get tokens; its keys and Passport client are kept,
 * so reactivation restores access. Revoked clients stay revoked (409).
 */
final class SetApiClientSuspension
{
    public function handle(ApiClient $client, bool $suspended, Actor $actor): ApiClient
    {
        return DB::transaction(static function () use ($client, $suspended, $actor): ApiClient {
            $client = ApiClient::query()->lockForUpdate()->findOrFail($client->id);
            $from = $client->status;
            $to = $suspended ? ApiClientStatus::Suspended : ApiClientStatus::Active;

            if ($from === $to) {
                return $client;
            }

            if ($from === ApiClientStatus::Revoked) {
                throw new ApiException('invalid_state_transition', status: 409, details: ['from' => $from->value, 'to' => $to->value]);
            }

            $client->forceFill(['status' => $to])->save();

            AuditLogger::log($suspended ? 'api_client.suspended' : 'api_client.reactivated', $client, actor: $actor);

            return $client;
        });
    }
}
