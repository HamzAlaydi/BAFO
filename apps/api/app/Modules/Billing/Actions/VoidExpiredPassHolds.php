<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\SponsoredPass;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `pending → void` for passes whose checkout hold expired (ARCHITECTURE §6.3, §12). A payment
 * that still succeeds later funds its slots anyway (the late confirmation of §6.4).
 */
final class VoidExpiredPassHolds
{
    public function handle(Actor $actor, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        return DB::transaction(static function () use ($actor, $now): int {
            $passes = SponsoredPass::query()
                ->where('status', PassStatus::Pending->value)
                ->where('hold_expires_at', '<=', $now)
                ->lockForUpdate()
                ->get();

            foreach ($passes as $pass) {
                $pass->forceFill(['status' => PassStatus::Void, 'voided_at' => $now, 'hold_expires_at' => null])->save();
                AuditLogger::log('sponsored_pass.voided', $pass, meta: ['reason' => 'hold_expired'], actor: $actor);
            }

            return $passes->count();
        });
    }
}
