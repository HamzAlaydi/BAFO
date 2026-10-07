<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Features\ReleaseScope;
use Illuminate\Testing\TestResponse;
use Tests\Support\Competitions\Fixtures;
use Tests\Support\Identity\Accounts;

/*
|--------------------------------------------------------------------------
| Release scope: Competitions (RELEASE_SCOPE.md §1.5, §1.6)
|--------------------------------------------------------------------------
|
| Field-level refusals on POST/PATCH /competitions and the invitation rows (422 with
| `errors.feature_disabled_field` on the exact path), the permission projection, and the
| existing records created while `full` that keep rendering and saving their other fields in
| `core`. The route gates (`feature:`) are covered by Platform/ReleaseScopeTest.
|
*/

beforeEach(function (): void {
    Accounts::seedCatalog();
    $this->category = Category::factory()->create();
    $this->region = Region::factory()->create();
    [$this->org, $this->owner] = Fixtures::issuer();
    Fixtures::signIn($this->owner);
});

/**
 * The body the core wizard sends after step 1 (RELEASE_SCOPE.md §2.1): the standard tier preset
 * and its rules plus a start price; `$rules` replaces rule keys.
 *
 * @param  array<string, mixed>  $overrides
 * @param  array<string, mixed>  $rules
 * @return array<string, mixed>
 */
function scopeCreateBody(Category $category, Region $region, array $overrides = [], array $rules = []): array
{
    $preset = CompetitionPreset::query()->where('code', 'tender_live_standard')->sole();

    return Fixtures::createBody($category, $region, [
        'preset_code' => $preset->code,
        'rules' => array_replace_recursive([...$preset->rules, 'start_price_minor' => 25_000_000], $rules),
        ...$overrides,
    ]);
}

/**
 * The error keys are flat paths (`rules.bafo_round.enabled`), so they are read from the bag
 * rather than through assertJsonPath's dot notation.
 */
function assertFieldRefused(TestResponse $response, string $path, string $locale = 'ar'): void
{
    $response->assertUnprocessable()->assertJsonPath('code', 'validation_failed');

    expect($response->json('errors'))->toHaveKey($path)
        ->and($response->json('errors')[$path])->toBe([__('errors.feature_disabled_field', [], $locale)]);
}

function assertFieldNotRefused(TestResponse $response, string $path): void
{
    expect($response->json('errors')[$path] ?? [])->not->toContain(__('errors.feature_disabled_field'));
}

describe('POST /competitions in core', function (): void {
    beforeEach(fn () => $this->releaseScope(ReleaseScope::Core));

    it('creates a draft from a tier preset (the core wizard body)', function (): void {
        $this->postJson('/api/app/v1/competitions', scopeCreateBody($this->category, $this->region))
            ->assertCreated()
            ->assertJsonPath('data.format', 'live')
            ->assertJsonPath('data.preset_code', 'tender_live_standard')
            ->assertJsonPath('data.rules.min_step_bps', 50)
            ->assertJsonPath('data.rules.final_window_minutes', null)
            ->assertJsonPath('data.rules.bafo_round.enabled', false)
            ->assertJsonPath('data.rules.min_participants', 2);
    });

    it('fills the rules from the tier preset when only preset_code is sent', function (): void {
        $body = Fixtures::createBody($this->category, $this->region, [
            'preset_code' => 'tender_live_protected',
            'rules' => ['start_price_minor' => 1_000_000],
        ]);

        $this->postJson('/api/app/v1/competitions', $body)
            ->assertCreated()
            ->assertJsonPath('data.rules.rank_visibility', 'none')
            ->assertJsonPath('data.rules.min_step_bps', 100)
            ->assertJsonPath('data.rules.auto_extend.window_seconds', 300)
            ->assertJsonPath('data.rules.final_window_minutes', null);
    });

    it('refuses a hidden value on its exact path', function (array $overrides, array $rules, string $path): void {
        assertFieldRefused($this->postJson('/api/app/v1/competitions', scopeCreateBody($this->category, $this->region, $overrides, $rules)), $path);

        expect(Competition::query()->count())->toBe(0);
    })->with([
        'sealed format' => [['format' => 'sealed'], ['must_beat' => null, 'rank_visibility' => 'none', 'min_step_bps' => null, 'auto_extend' => ['enabled' => false]], 'format'],
        'BAFO round' => [[], ['bafo_round' => ['enabled' => true, 'duration_minutes' => 60]], 'rules.bafo_round.enabled'],
        'final pricing window' => [[], ['final_window_minutes' => 30], 'rules.final_window_minutes'],
        'reserve price' => [[], ['reserve_price_minor' => 21_000_000], 'rules.reserve_price_minor'],
        'halala granularity' => [[], ['amount_granularity_minor' => 1], 'rules.amount_granularity_minor'],
        'result publication other than the preset' => [[], ['result_publication' => 'outcome_and_amount', 'show_prices' => true], 'rules.result_publication'],
        'minimum participants other than the preset' => [[], ['min_participants' => 5], 'rules.min_participants'],
        'an untiered preset' => [['preset_code' => 'standard_live_tender'], [], 'preset_code'],
    ]);

    it('names the refusal in English too', function (): void {
        assertFieldRefused(
            $this->postJson('/api/app/v1/competitions', scopeCreateBody($this->category, $this->region, rules: ['final_window_minutes' => 30]), ['Accept-Language' => 'en']),
            'rules.final_window_minutes',
            'en',
        );
        expect(__('errors.feature_disabled_field', [], 'en'))->toBe('This option is not available in this release.');
    });

    it('accepts the preset values of the advanced keys and the switches turned off', function (): void {
        $this->postJson('/api/app/v1/competitions', scopeCreateBody($this->category, $this->region, rules: [
            'reserve_price_minor' => null,
            'amount_granularity_minor' => 100,
            'final_window_minutes' => null,
            'bafo_round' => ['enabled' => false, 'duration_minutes' => null],
            'result_publication' => 'outcome_only',
            'min_participants' => 2,
            // Core controls of the «إعدادات متقدمة» disclosure stay free.
            'must_beat' => 'best',
            'show_prices' => true,
            'rank_visibility' => 'full',
            'auto_extend' => ['enabled' => true, 'window_seconds' => 120, 'by_seconds' => 60, 'max_extensions' => 3],
        ]))->assertCreated()
            ->assertJsonPath('data.rules.must_beat', 'best')
            ->assertJsonPath('data.rules.rank_visibility', 'full')
            ->assertJsonPath('data.rules.auto_extend.by_seconds', 60);
    });

    it('takes the simple tier minimum of one participant from its preset', function (): void {
        $preset = CompetitionPreset::query()->where('code', 'tender_live_simple')->sole();

        $this->postJson('/api/app/v1/competitions', scopeCreateBody($this->category, $this->region, ['preset_code' => $preset->code], [...$preset->rules]))
            ->assertCreated()
            ->assertJsonPath('data.rules.min_participants', 1);
    });

    it('requires a preset_code', function (): void {
        $body = Fixtures::createBody($this->category, $this->region);
        unset($body['preset_code']);

        $this->postJson('/api/app/v1/competitions', $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preset_code']);

        $this->postJson('/api/app/v1/competitions', [...$body, 'preset_code' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preset_code']);
    });
});

describe('POST /competitions in full', function (): void {
    it('accepts every hidden value again', function (array $overrides, array $rules, string $path): void {
        $response = $this->postJson('/api/app/v1/competitions', scopeCreateBody($this->category, $this->region, $overrides, $rules));

        assertFieldNotRefused($response, $path);
        $response->assertCreated();
    })->with([
        'sealed format' => [['format' => 'sealed', 'preset_code' => 'sealed_rfq'], ['must_beat' => null, 'rank_visibility' => 'none', 'min_step_bps' => null, 'auto_extend' => ['enabled' => false]], 'format'],
        'BAFO round' => [[], ['bafo_round' => ['enabled' => true, 'duration_minutes' => 60]], 'rules.bafo_round.enabled'],
        'final pricing window' => [[], ['final_window_minutes' => 30], 'rules.final_window_minutes'],
        'reserve price' => [[], ['reserve_price_minor' => 21_000_000], 'rules.reserve_price_minor'],
        'halala granularity' => [[], ['amount_granularity_minor' => 1], 'rules.amount_granularity_minor'],
        'result publication' => [[], ['result_publication' => 'outcome_and_amount', 'show_prices' => true], 'rules.result_publication'],
        'minimum participants' => [[], ['min_participants' => 5], 'rules.min_participants'],
        'an untiered preset' => [['preset_code' => 'standard_live_tender'], [], 'preset_code'],
    ]);

    it('does not require a preset_code', function (): void {
        $body = Fixtures::createBody($this->category, $this->region);
        unset($body['preset_code']);

        $this->postJson('/api/app/v1/competitions', $body)->assertCreated();
    });
});

describe('PATCH /competitions/{id} in core on a draft created in full', function (): void {
    beforeEach(function (): void {
        // A sealed tender with a BAFO round, a reserve, halala granularity, no result publication
        // and a minimum of four, created while the scope was `full`.
        $this->draft = Fixtures::draft($this->org, $this->owner, [
            'format' => Format::Sealed,
            'preset_code' => 'sealed_rfq',
            'min_step_bps' => null,
            'must_beat' => null,
            'rank_visibility' => 'none',
            'auto_extend_enabled' => false,
            'auto_extend_window_seconds' => null,
            'auto_extend_by_seconds' => null,
            'auto_extend_max' => null,
            'final_window_minutes' => null,
            'bafo_round_enabled' => true,
            'bafo_duration_minutes' => 60,
            'reserve_price_minor' => 21_000_000,
            'amount_granularity_minor' => 1,
            'result_publication' => 'none',
            'min_participants' => 4,
        ]);
        $this->releaseScope(ReleaseScope::Core);
    });

    it('renders it with every stored value', function (): void {
        $this->getJson('/api/app/v1/competitions/'.$this->draft->public_id)
            ->assertOk()
            ->assertJsonPath('data.format', 'sealed')
            ->assertJsonPath('data.rules.bafo_round.enabled', true)
            ->assertJsonPath('data.rules.reserve_price_minor', 21_000_000)
            ->assertJsonPath('data.rules.amount_granularity_minor', 1)
            ->assertJsonPath('data.rules.result_publication', 'none')
            ->assertJsonPath('data.rules.min_participants', 4);
    });

    it('saves other fields, alone or with the unchanged rules object and preset', function (): void {
        $this->patchJson('/api/app/v1/competitions/'.$this->draft->public_id, ['title' => 'عنوان جديد'])
            ->assertOk()
            ->assertJsonPath('data.title', 'عنوان جديد');

        $rules = $this->getJson('/api/app/v1/competitions/'.$this->draft->public_id)->json('data.rules');

        $this->patchJson('/api/app/v1/competitions/'.$this->draft->public_id, [
            'format' => 'sealed',
            'preset_code' => 'sealed_rfq',
            'rules' => [...$rules, 'start_price_minor' => 30_000_000],
        ])
            ->assertOk()
            ->assertJsonPath('data.rules.start_price_minor', 30_000_000)
            ->assertJsonPath('data.rules.bafo_round.enabled', true)
            ->assertJsonPath('data.rules.reserve_price_minor', 21_000_000);
    });

    it('refuses a change towards a hidden value', function (array $body, string $path): void {
        assertFieldRefused($this->patchJson('/api/app/v1/competitions/'.$this->draft->public_id, $body), $path);
    })->with([
        'another reserve' => [['rules' => ['reserve_price_minor' => 22_000_000]], 'rules.reserve_price_minor'],
        'a final window' => [['rules' => ['final_window_minutes' => 30]], 'rules.final_window_minutes'],
        'another result publication' => [['rules' => ['result_publication' => 'outcome_and_amount']], 'rules.result_publication'],
        'another minimum' => [['rules' => ['min_participants' => 6]], 'rules.min_participants'],
        'another untiered preset' => [['preset_code' => 'standard_live_tender'], 'preset_code'],
    ]);

    it('lets the issuer move the draft to the core rules', function (): void {
        $preset = CompetitionPreset::query()->where('code', 'tender_live_standard')->sole();

        $this->patchJson('/api/app/v1/competitions/'.$this->draft->public_id, [
            'format' => 'live',
            'preset_code' => $preset->code,
            'rules' => [...$preset->rules, 'start_price_minor' => 25_000_000, 'reserve_price_minor' => null],
        ])
            ->assertOk()
            ->assertJsonPath('data.format', 'live')
            ->assertJsonPath('data.preset_code', 'tender_live_standard')
            ->assertJsonPath('data.rules.bafo_round.enabled', false)
            ->assertJsonPath('data.rules.amount_granularity_minor', 100)
            ->assertJsonPath('data.rules.reserve_price_minor', null);

        // Back to sealed or a BAFO round is now a change towards a hidden value.
        assertFieldRefused($this->patchJson('/api/app/v1/competitions/'.$this->draft->public_id, ['format' => 'sealed']), 'format');
        assertFieldRefused(
            $this->patchJson('/api/app/v1/competitions/'.$this->draft->public_id, ['rules' => ['bafo_round' => ['enabled' => true]]]),
            'rules.bafo_round.enabled',
        );
    });
});

describe('invitation rows', function (): void {
    beforeEach(function (): void {
        $this->draft = Fixtures::draft($this->org, $this->owner);
    });

    it('refuses vendor references and sponsored rows in core', function (array $row, string $path): void {
        $this->releaseScope(ReleaseScope::Core);

        assertFieldRefused(
            $this->postJson('/api/app/v1/competitions/'.$this->draft->public_id.'/invitations', ['invitations' => [['email' => 'ok@example.sa'], $row]]),
            $path,
        );

        expect(Invitation::query()->where('competition_id', $this->draft->id)->count())->toBe(0);
    })->with([
        'vendor_id' => [['vendor_id' => '01j00000000000000000000000'], 'invitations.1.vendor_id'],
        'vendor_external' => [['vendor_external' => ['system' => 'sap', 'id' => 'V-1']], 'invitations.1.vendor_external'],
        'sponsored' => [['email' => 'paid@example.sa', 'sponsored' => true], 'invitations.1.sponsored'],
    ]);

    it('invites by e-mail in core, sponsored false included', function (): void {
        $this->releaseScope(ReleaseScope::Core);

        $this->postJson('/api/app/v1/competitions/'.$this->draft->public_id.'/invitations', ['invitations' => [
            ['email' => 'a@example.sa', 'name' => 'شركة أ'],
            ['email' => 'b@example.sa', 'sponsored' => false],
        ]])->assertCreated()->assertJsonCount(2, 'data');
    });

    it('accepts sponsored rows in full', function (): void {
        $response = $this->postJson('/api/app/v1/competitions/'.$this->draft->public_id.'/invitations', ['invitations' => [
            ['email' => 'paid@example.sa', 'sponsored' => true],
        ]]);

        assertFieldNotRefused($response, 'invitations.0.sponsored');
        $response->assertCreated();
    });

    it('refuses turning sponsored on through PATCH in core unless the invitation already is', function (): void {
        [$plain, $covered] = Fixtures::draftInvitations($this->draft);
        $covered->forceFill(['sponsored_requested' => true])->save();
        $this->releaseScope(ReleaseScope::Core);

        $base = '/api/app/v1/competitions/'.$this->draft->public_id.'/invitations/';

        assertFieldRefused($this->patchJson($base.$plain->public_id, ['sponsored' => true]), 'sponsored');
        $this->patchJson($base.$plain->public_id, ['name' => 'اسم', 'sponsored' => false])->assertOk();
        $this->patchJson($base.$covered->public_id, ['name' => 'مغطاة', 'sponsored' => true])->assertOk();
        $this->patchJson($base.$covered->public_id, ['sponsored' => false])->assertOk();

        expect($covered->refresh()->sponsored_requested)->toBeFalse();
    });
});

describe('permissions projection', function (): void {
    it('turns can_extend off in core', function (): void {
        $live = Competition::factory()->withoutFinalWindow()->live()->createdBy($this->owner)->create(['organization_id' => $this->org->id]);
        $uri = '/api/app/v1/competitions/'.$live->public_id;

        $this->getJson($uri)->assertOk()->assertJsonPath('data.permissions.can_extend', true);

        $this->releaseScope(ReleaseScope::Core);
        $this->getJson($uri)->assertOk()
            ->assertJsonPath('data.permissions.can_extend', false)
            ->assertJsonPath('data.permissions.can_cancel', true);
    });

    it('turns can_start_bafo off in core while a round in progress keeps rendering', function (): void {
        $closed = Competition::factory()->sealed()->closed()->createdBy($this->owner)->create(['organization_id' => $this->org->id]);
        $uri = '/api/app/v1/competitions/'.$closed->public_id;

        $this->getJson($uri)->assertOk()->assertJsonPath('data.permissions.can_start_bafo', true);

        $this->releaseScope(ReleaseScope::Core);
        $this->getJson($uri)->assertOk()
            ->assertJsonPath('data.permissions.can_start_bafo', false)
            ->assertJsonPath('data.permissions.can_award', true)
            ->assertJsonPath('data.format', 'sealed');

        BafoRound::factory()->create(['competition_id' => $closed->id]);
        $closed->forceFill(['status' => CompetitionStatus::BafoRound])->save();

        $this->getJson($uri)->assertOk()->assertJsonPath('data.status', 'bafo_round');
    });

    it('turns can_manage_sponsorship off in core', function (): void {
        $uri = '/api/app/v1/competitions/'.Fixtures::draft($this->org, $this->owner)->public_id;

        $this->getJson($uri)->assertOk()->assertJsonPath('data.permissions.can_manage_sponsorship', true);

        $this->releaseScope(ReleaseScope::Core);
        $this->getJson($uri)->assertOk()
            ->assertJsonPath('data.permissions.can_manage_sponsorship', false)
            ->assertJsonPath('data.permissions.can_invite', true)
            ->assertJsonPath('data.permissions.can_publish', true);
    });
});
