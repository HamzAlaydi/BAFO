<?php

declare(strict_types=1);

use App\Modules\Platform\Enums\LegalDocumentCode;
use App\Modules\Platform\Models\LegalDocument;

it('returns the latest published version in the request locale without a token', function () {
    LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2026-10-01')->create([
        'published_at' => now()->subDays(10),
    ]);
    $latest = LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2026-11-01')->create([
        'title' => 'الشروط والأحكام',
        'body_markdown' => "# الشروط\n\nنص.",
        'published_at' => now()->subDay(),
    ]);
    LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2027-01-01')->draft()->create();
    LegalDocument::factory()->forCode(LegalDocumentCode::Terms, 'ar', '2027-02-01')->create(['published_at' => now()->addWeek()]);

    $response = $this->getJson('/api/app/v1/legal/terms')
        ->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('data', [
            'code' => 'terms',
            'locale' => 'ar',
            'version' => '2026-11-01',
            'title' => 'الشروط والأحكام',
            'body_markdown' => "# الشروط\n\nنص.",
            'published_at' => $latest->published_at?->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ]);

    expect($response->json('data.published_at'))->toBeIso8601Utc();
});

it('serves the English version with Accept-Language: en', function () {
    LegalDocument::factory()->forCode(LegalDocumentCode::Privacy, 'ar')->create(['title' => 'سياسة الخصوصية']);
    LegalDocument::factory()->forCode(LegalDocumentCode::Privacy, 'en')->create(['title' => 'Privacy policy']);

    $this->getJson('/api/app/v1/legal/privacy', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.title', 'Privacy policy');
});

it('answers not_found when no version is published in the locale', function () {
    LegalDocument::factory()->forCode(LegalDocumentCode::Refund, 'en')->create();
    LegalDocument::factory()->forCode(LegalDocumentCode::Refund, 'ar')->draft()->create();

    $this->getJson('/api/app/v1/legal/refund')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

it('answers not_found for an unknown code', function () {
    $this->getJson('/api/app/v1/legal/cookies')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

it('accepts every contract code', function (LegalDocumentCode $code) {
    LegalDocument::factory()->forCode($code, 'ar')->create();

    $this->getJson('/api/app/v1/legal/'.$code->value)
        ->assertOk()
        ->assertJsonPath('data.code', $code->value);
})->with(LegalDocumentCode::cases());
