<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * The module migrations (ARCHITECTURE §3.5, §5.2–§5.9). RefreshDatabase has already run
 * `migrate:fresh` before the first test of the file.
 */

/**
 * @return array<string, list<string>>
 */
function schemaExpectedColumns(): array
{
    return require __DIR__.'/Fixtures/columns.php';
}

/**
 * @return array<string, array{0: string, 1: int, 2: int}>
 */
function schemaModuleRanges(): array
{
    return [
        'Catalog' => ['Catalog', 100, 199],
        'Identity' => ['Identity', 200, 299],
        'Integrations' => ['Integrations', 300, 399],
        'Competitions' => ['Competitions', 400, 499],
        'Bidding' => ['Bidding', 500, 599],
        'Billing' => ['Billing', 600, 699],
        'Notifications' => ['Notifications', 700, 799],
        'Admin' => ['Admin', 800, 899],
    ];
}

it('creates every module table with exactly the contract columns', function (string $table) {
    $expected = schemaExpectedColumns()[$table];

    expect(Schema::hasTable($table))->toBeTrue()
        ->and(Schema::getColumnListing($table))->toEqualCanonicalizing($expected);
})->with(fn (): array => array_keys(schemaExpectedColumns()));

it('names module migrations inside the module range', function (string $module, int $from, int $to) {
    $files = glob(app_path("Modules/{$module}/Database/Migrations/*.php")) ?: [];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        expect(basename($file))->toMatch('/^2026_01_01_\d{6}_[a-z0-9_]+\.php$/');

        $number = (int) substr(basename($file), 11, 6);

        expect($number)->toBeGreaterThanOrEqual($from)->toBeLessThanOrEqual($to);
    }
})->with(schemaModuleRanges());

it('uses microsecond timestamps on the precise tables only (§4.3)', function () {
    $precise = [
        'competitions', 'competition_extensions', 'offers', 'offer_rejections', 'offer_voids',
        'participant_standings', 'competition_live_states', 'bafo_rounds', 'awards',
    ];

    $columns = DB::select(
        "select table_name, column_name, datetime_precision from information_schema.columns
         where table_schema = 'public' and data_type = 'timestamp with time zone' and table_name = any(?::text[])",
        ['{'.implode(',', array_keys(schemaExpectedColumns())).'}'],
    );

    expect($columns)->not->toBeEmpty();

    foreach ($columns as $column) {
        $expected = in_array($column->table_name, $precise, true)
            || ($column->table_name === 'webhook_events' && $column->column_name === 'occurred_at') ? 6 : 0;

        expect((int) $column->datetime_precision)->toBe($expected, "{$column->table_name}.{$column->column_name}");
    }
});

it('creates the reference and invoice number sequences', function (string $sequence) {
    $first = (int) DB::scalar("select nextval('{$sequence}')");

    expect((int) DB::scalar("select nextval('{$sequence}')"))->toBe($first + 1);
})->with(['competition_reference_seq', 'invoice_number_seq']);

it('keeps the ref columns unconstrained and the fk columns constrained (§3.5)', function () {
    $foreignKeys = collect(DB::select(
        "select tc.table_name, kcu.column_name, ccu.table_name as foreign_table, rc.delete_rule
         from information_schema.table_constraints tc
         join information_schema.key_column_usage kcu on kcu.constraint_name = tc.constraint_name
         join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name
         join information_schema.referential_constraints rc on rc.constraint_name = tc.constraint_name
         where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public'"
    ))->mapWithKeys(fn (object $fk): array => ["{$fk->table_name}.{$fk->column_name}" => "{$fk->foreign_table}:{$fk->delete_rule}"]);

    // ref → t: indexed, no FK (a later module, Passport, or the Admin module).
    foreach ([
        'external_refs.created_by_api_client_id', 'api_clients.oauth_client_id', 'competitions.cancelled_by_admin_id',
        'competition_extensions.triggered_by_offer_id', 'competition_extensions.actor_admin_id',
        'competition_live_states.leader_offer_id', 'offer_voids.voided_by_admin_id', 'coupons.created_by_admin_id',
        'payments.refunded_by_admin_id', 'subscriptions.granted_by_admin_id',
    ] as $ref) {
        expect($foreignKeys->has($ref))->toBeFalse("{$ref} must be unconstrained");
    }

    // A sample of fk → t with the contract's delete rules.
    expect($foreignKeys->all())->toMatchArray([
        'organizations.region_id' => 'regions:RESTRICT',
        'organizations.logo_file_id' => 'files:SET NULL',
        'memberships.organization_id' => 'organizations:CASCADE',
        'memberships.user_id' => 'users:CASCADE',
        'vendors.linked_organization_id' => 'organizations:SET NULL',
        'invitations.vendor_id' => 'vendors:SET NULL',
        'participants.invitation_id' => 'invitations:RESTRICT',
        'comments.parent_id' => 'comments:CASCADE',
        'offers.competition_id' => 'competitions:RESTRICT',
        'offers.participant_id' => 'participants:RESTRICT',
        'participant_standings.participant_id' => 'participants:CASCADE',
        'competition_live_states.competition_id' => 'competitions:CASCADE',
        'awards.offer_id' => 'offers:RESTRICT',
        'sponsored_passes.sponsorship_id' => 'competition_sponsorships:CASCADE',
        'subscriptions.replaces_subscription_id' => 'subscriptions:SET NULL',
        'invoices.original_invoice_id' => 'invoices:SET NULL',
        'device_tokens.user_id' => 'users:CASCADE',
    ]);
});

it('rolls every module migration back and re-applies it', function () {
    $files = [];

    foreach (schemaModuleRanges() as [$module]) {
        array_push($files, ...(glob(app_path("Modules/{$module}/Database/Migrations/*.php")) ?: []));
    }

    // Migration order is the file name order (2026_01_01_HHMMSS), not the module path order.
    usort($files, static fn (string $a, string $b): int => strcmp(basename($a), basename($b)));

    foreach (array_reverse($files) as $file) {
        (require $file)->down();
    }

    foreach (array_keys(schemaExpectedColumns()) as $table) {
        expect(Schema::hasTable($table))->toBeFalse("{$table} should be dropped");
    }

    foreach ($files as $file) {
        (require $file)->up();
    }

    foreach (array_keys(schemaExpectedColumns()) as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} should exist again");
    }
});
