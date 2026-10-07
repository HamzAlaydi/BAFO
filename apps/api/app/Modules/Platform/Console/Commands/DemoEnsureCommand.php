<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use App\Modules\Billing\Actions\StartTrial;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Database\Seeders\BillingDemoSeeder;
use App\Modules\Billing\Models\Plan;
use App\Modules\Competitions\Database\Seeders\CompetitionsDemoSeeder;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Models\Organization;
use App\Support\Auth\Actor;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use Throwable;

/**
 * `demo:ensure` repairs a partially seeded demo environment without wiping it (hosted demos
 * boot this on every start): every demo organization of DemoSeeder::ORGANIZATIONS that has
 * a plan but no active subscription gets its demo period, and the demo competitions are
 * created when the issuer has none. Idempotent; refuses production.
 */
final class DemoEnsureCommand extends Command
{
    protected $signature = 'demo:ensure';

    protected $description = 'Idempotently complete the demo data (subscriptions, demo competitions); never in production';

    public function handle(AccessPolicy $access, StartTrial $startTrial, BillingDemoSeeder $billing): int
    {
        if (app()->environment('production')) {
            $this->components->error('demo:ensure refuses to run in production.');

            return self::FAILURE;
        }

        foreach (DemoSeeder::ORGANIZATIONS as $key => $definition) {
            $planCode = $definition['plan'];
            $organization = Organization::query()->where('cr_number', $definition['cr_number'])->first();

            if ($planCode === null || $organization === null || $access->activeSubscription($organization) !== null) {
                continue;
            }

            try {
                if ($planCode === 'trial') {
                    $startTrial->handle($organization, Actor::system());
                } else {
                    $billing->grantPaidPeriod($organization, Plan::query()->where('code', $planCode)->firstOrFail());
                }
                $this->components->info("Subscription ensured for [{$key}] ({$planCode}).");
            } catch (Throwable $e) {
                $this->components->error("Subscription for [{$key}] failed: {$e->getMessage()}");
            }
        }

        $issuer = Organization::query()->where('cr_number', DemoSeeder::ORGANIZATIONS['issuer']['cr_number'])->first();

        if ($issuer !== null && ! Competition::query()->where('organization_id', $issuer->id)->exists()) {
            try {
                $this->call('db:seed', ['--class' => CompetitionsDemoSeeder::class, '--force' => true]);
                $this->components->info('Demo competitions created.');
            } catch (Throwable $e) {
                $this->components->error("Demo competitions failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
