<?php

declare(strict_types=1);

use App\Modules\Platform\Database\Seeders\PlatformDemoSeeder;
use App\Modules\Platform\Database\Seeders\PlatformReferenceSeeder;
use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;
use App\Support\Settings\AppSetting;
use App\Support\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

it('seeds a published placeholder legal document for every code in AR and EN', function () {
    $this->seed(PlatformReferenceSeeder::class);

    expect(LegalDocument::query()->count())->toBe(count(LegalDocumentCode::cases()) * 2);

    foreach (LegalDocumentCode::cases() as $code) {
        foreach (['ar', 'en'] as $locale) {
            $document = LegalDocument::latestPublished($code, $locale);

            expect($document)->not->toBeNull()
                ->and($document?->version)->toBe('2026-10-01')
                ->and($document?->body_markdown)->toStartWith(PlatformReferenceSeeder::draftNotice($locale))
                ->and($document?->title)->toBe($code->label($locale));
        }
    }

    // The Arabic documents open with an Arabic draft notice, never with an English line.
    expect(LegalDocument::latestPublished(LegalDocumentCode::Terms, 'ar')?->body_markdown)->toStartWith('مسودة:')
        ->and(LegalDocument::latestPublished(LegalDocumentCode::Terms, 'en')?->body_markdown)->toStartWith(PlatformReferenceSeeder::DRAFT_MARKER);

    $this->getJson('/api/app/v1/app-config')
        ->assertJsonPath('data.legal.terms', ['version' => '2026-10-01'])
        ->assertJsonPath('data.legal.competition_rules', ['version' => '2026-10-01']);
});

it('seeds the registered setting defaults without overwriting edited values', function () {
    app(Settings::class)->set('app.min_version.ios', '3.0.0', 1);

    $this->seed(PlatformReferenceSeeder::class);

    expect(AppSetting::query()->where('key', 'app.maintenance.enabled')->sole()->value)->toBeFalse()
        ->and(AppSetting::query()->where('key', 'app.min_version.ios')->sole()->value)->toBe('3.0.0')
        ->and(AppSetting::query()->count())->toBe(count(app(Settings::class)->registeredDefaults()));
});

it('is idempotent', function () {
    $this->seed(PlatformReferenceSeeder::class);
    $publishedAt = LegalDocument::query()->orderBy('id')->first()?->published_at;

    $this->travel(1)->day();
    $this->seed(PlatformReferenceSeeder::class);

    expect(LegalDocument::query()->count())->toBe(10)
        ->and(LegalDocument::query()->orderBy('id')->first()?->published_at?->equalTo($publishedAt))->toBeTrue();
});

it('runs every reference seeder that exists, in module order', function () {
    expect(DatabaseSeeder::MODULES)->toBe([
        'Platform', 'Catalog', 'Identity', 'Integrations', 'Competitions', 'Bidding', 'Billing', 'Notifications', 'Admin',
    ])->and(DatabaseSeeder::referenceSeeders()[0])->toBe(PlatformReferenceSeeder::class);

    $order = array_map(
        fn (string $class): int|false => array_search(explode('\\', $class)[2], DatabaseSeeder::MODULES, true),
        DatabaseSeeder::referenceSeeders(),
    );

    expect($order)->toBe(array_values(array_unique($order)))
        ->and($order)->toEqual(collect($order)->sort()->values()->all());
});

it('runs the module demo seeders that exist, after the reference data', function () {
    expect(DemoSeeder::MODULES)->toContain('Identity', 'Billing', 'Competitions')
        ->and(array_search('Billing', DemoSeeder::MODULES, true))->toBeLessThan(array_search('Competitions', DemoSeeder::MODULES, true));

    foreach (DemoSeeder::demoSeeders() as $seeder) {
        expect($seeder)->toEndWith('DemoSeeder');
    }
});

it('seeds placeholder store links and support contacts for the demo only', function () {
    $this->seed(PlatformReferenceSeeder::class);

    $this->getJson('/api/app/v1/app-config')
        ->assertJsonPath('data.store_links', ['ios' => '', 'android' => ''])
        ->assertJsonPath('data.support', ['email' => '', 'phone' => '', 'whatsapp' => '']);

    $this->seed(PlatformDemoSeeder::class);

    $data = $this->getJson('/api/app/v1/app-config')->assertOk()->json('data');

    expect($data['store_links'])->toBe(PlatformDemoSeeder::STORE_LINKS)
        ->and($data['support'])->toBe(PlatformDemoSeeder::SUPPORT)
        ->and($data['store_links']['ios'])->toStartWith('https://bafo.example/')
        ->and($data['store_links']['android'])->toStartWith('https://bafo.example/')
        ->and($data['support']['email'])->toEndWith('@bafo.example')
        ->and(DemoSeeder::demoSeeders()[0])->toBe(PlatformDemoSeeder::class);
});

it('refuses to seed demo data in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    app(DemoSeeder::class)->run();
})->throws(RuntimeException::class, 'production');

it('holds the demo credentials for every demo organization user', function () {
    $emails = collect(DemoSeeder::ORGANIZATIONS)->flatMap(fn (array $org) => array_column($org['users'], 'email'));

    expect($emails)->toHaveCount(7)
        ->and($emails->unique())->toHaveCount(7)
        ->and(DemoSeeder::user('issuer.owner')['email'])->toBe($emails->first())
        ->and(DemoSeeder::user('supplier_b.owner')['organization'])->toBe('supplier_b')
        ->and(DemoSeeder::PASSWORD)->not->toBeEmpty()
        ->and(DemoSeeder::ADMIN_EMAIL)->not->toBeEmpty();
});
