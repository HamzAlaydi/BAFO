<?php

declare(strict_types=1);

use App\Support\Settings\AppSetting;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('registers the Platform defaults of §15.3', function () {
    $settings = app(Settings::class);

    expect($settings->get('app.min_version.ios'))->toBe('1.0.0')
        ->and($settings->get('app.latest_version.android'))->toBe('1.0.0')
        ->and($settings->get('app.maintenance.enabled'))->toBeFalse()
        ->and($settings->get('app.maintenance.message'))->toBe(['ar' => '', 'en' => ''])
        ->and($settings->get('app.store_links'))->toBe(['ios' => '', 'android' => ''])
        ->and($settings->get('app.support'))->toBe(['email' => '', 'phone' => '', 'whatsapp' => '']);
});

it('prefers the stored value, then the registered default, then the caller default', function () {
    $settings = app(Settings::class);
    $settings->defaults(['competitions.max_participants' => 200]);

    expect($settings->get('competitions.max_participants', 5))->toBe(200)
        ->and($settings->get('unknown.key', 'fallback'))->toBe('fallback')
        ->and($settings->get('unknown.key'))->toBeNull();

    $settings->set('competitions.max_participants', 150, null);

    expect($settings->get('competitions.max_participants', 5))->toBe(150);
});

it('stores any JSON value in app_settings', function (mixed $value) {
    $settings = app(Settings::class);
    $settings->set('test.value', $value, 12);

    $settings->flush();

    // jsonb may reorder object keys: compare by value, and keep the PHP type.
    expect($settings->get('test.value'))->toEqual($value)
        ->and(get_debug_type($settings->get('test.value')))->toBe(get_debug_type($value))
        ->and(AppSetting::query()->where('key', 'test.value')->sole()->updated_by_admin_id)->toBe(12);
})->with([
    'bool' => false,
    'int' => 1000000000000,
    'string' => '1.2.0',
    'list' => [[10, 2]],
    'object' => [['min' => 30, 'max' => 600]],
    'localised' => [['ar' => 'صيانة', 'en' => 'Maintenance']],
]);

it('caches the table for 60 seconds under settings:all and refreshes it on set', function () {
    $settings = app(Settings::class);
    $settings->set('app.maintenance.enabled', true, null);

    expect($settings->get('app.maintenance.enabled'))->toBeTrue()
        ->and(Cache::get('settings:all'))->toMatchArray(['app.maintenance.enabled' => true]);

    // A write that bypasses Settings is not seen until the cache is cleared.
    DB::table('app_settings')->where('key', 'app.maintenance.enabled')->update(['value' => 'false']);
    expect($settings->get('app.maintenance.enabled'))->toBeTrue();

    $settings->set('app.support', ['email' => 'a@b.test', 'phone' => '', 'whatsapp' => ''], null);
    expect($settings->get('app.maintenance.enabled'))->toBeFalse();
});

it('forgets a stored value so the default applies again', function () {
    $settings = app(Settings::class);
    $settings->set('app.min_version.ios', '9.9.9', null);
    $settings->forget('app.min_version.ios');

    expect($settings->get('app.min_version.ios'))->toBe('1.0.0');
});

it('lists every known setting', function () {
    $settings = app(Settings::class);
    $settings->set('app.min_version.ios', '2.0.0', null);

    expect($settings->all())
        ->toHaveKey('app.maintenance.enabled', false)
        ->toHaveKey('app.min_version.ios', '2.0.0')
        ->and($settings->has('app.support'))->toBeTrue()
        ->and($settings->has('nope'))->toBeFalse();
});
