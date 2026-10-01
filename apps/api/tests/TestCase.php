<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Clock\CarbonDbClock;
use App\Support\Clock\DbClock;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\NullHandler;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Symfony's test requests send "Accept-Language: en-us" by default. Clear it so
        // requests behave like a client that sends no language (API default: ar).
        // Per-request headers still win: $this->getJson($uri, ['Accept-Language' => 'en']).
        $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => '']);

        // The database clock follows $this->travelTo() in tests (ARCHITECTURE §4.2). A test that
        // needs clock_timestamp() itself rebinds App\Support\Clock\PostgresDbClock.
        $this->app->instance(DbClock::class, new CarbonDbClock);

        // Files (invoice and report PDFs, attachments, logos) never reach the real storage/app:
        // every test writes to per-process fake disks. A test may call Storage::fake() again.
        Storage::fake('private');
        Storage::fake('public');

        // Logs never reach storage/logs: the default and the dedicated channels (CONVENTIONS §2.3)
        // are discarded. A test that checks a channel installs its own handler (e.g. Notifications'
        // DeliveryTest). The default is set here, not through LOG_CHANNEL, because env() reads the
        // string "null" as null.
        config()->set('logging.default', 'null');

        foreach (['api_access', 'push'] as $channel) {
            config()->set("logging.channels.{$channel}", ['driver' => 'monolog', 'handler' => NullHandler::class]);
        }
    }

    /**
     * Runs before RefreshDatabase wipes anything: refuse any database that is not a
     * *_test database (e.g. when a cached config points at the dev database `bafo`).
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (preg_match('/_test(_test_\d+)?$/', $database) !== 1) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}]: use a *_test database. "
                .'Run `php artisan config:clear` and check phpunit.xml.'
            );
        }

        return parent::setUpTraits();
    }
}
