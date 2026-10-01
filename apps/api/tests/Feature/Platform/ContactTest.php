<?php

declare(strict_types=1);

use App\Modules\Platform\Enums\ContactStatus;
use App\Modules\Platform\Models\ContactMessage;
use App\Support\Audit\AuditLog;
use App\Support\Auth\ActorType;

function platformContactPayload(array $overrides = []): array
{
    return [
        'name' => 'سارة العتيبي',
        'email' => 'Sara@Example.TEST',
        'phone' => '+966512345678',
        'company' => 'شركة المثال',
        'subject' => 'استفسار عن الاشتراك',
        'message' => 'أرغب في معرفة تفاصيل الباقات.',
        ...$overrides,
    ];
}

it('stores a contact message without a token and returns its id', function () {
    $response = $this->postJson('/api/app/v1/contact', platformContactPayload(), ['User-Agent' => 'BafoTest/1.0'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id'], 'meta' => ['server_time']]);

    $message = ContactMessage::query()->sole();

    expect($response->json('data'))->toBe(['id' => $message->public_id])
        ->and($message->email)->toBe('sara@example.test')
        ->and($message->name)->toBe('سارة العتيبي')
        ->and($message->locale)->toBe('ar')
        ->and($message->status)->toBe(ContactStatus::New)
        ->and($message->ip)->toBe('127.0.0.1')
        ->and($message->user_agent)->toBe('BafoTest/1.0');
});

it('records the locale of the request', function () {
    $this->postJson('/api/app/v1/contact', platformContactPayload(), ['Accept-Language' => 'en'])->assertCreated();

    expect(ContactMessage::query()->sole()->locale)->toBe('en');
});

it('audits the message as a guest action', function () {
    $this->postJson('/api/app/v1/contact', platformContactPayload())->assertCreated();

    $entry = AuditLog::query()->sole();

    expect($entry->action)->toBe('contact_message.received')
        ->and($entry->actor_type)->toBe(ActorType::Guest)
        ->and($entry->subject_type)->toBe('contact_message')
        ->and($entry->subject_public_id)->toBe(ContactMessage::query()->sole()->public_id);
});

it('accepts a message without the optional fields', function () {
    $this->postJson('/api/app/v1/contact', platformContactPayload(['phone' => null, 'company' => null]))->assertCreated();

    expect(ContactMessage::query()->sole())
        ->phone->toBeNull()
        ->company->toBeNull();
});

it('validates the fields', function () {
    $this->postJson('/api/app/v1/contact', [
        'email' => 'not-an-email',
        'phone' => '0512345678',
        'message' => str_repeat('a', 5001),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['name', 'email', 'phone', 'subject', 'message']);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('explains the phone format in the request language', function () {
    $this->postJson('/api/app/v1/contact', platformContactPayload(['phone' => '0512345678']), ['Accept-Language' => 'en'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.phone.0', 'Enter a Saudi mobile number in the format +9665XXXXXXXX.');
});

it('rejects a filled honeypot', function () {
    $this->postJson('/api/app/v1/contact', platformContactPayload(['website_url' => 'https://spam.test']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website_url']);

    expect(ContactMessage::query()->count())->toBe(0);
});

it('accepts an empty honeypot', function () {
    $this->postJson('/api/app/v1/contact', platformContactPayload(['website_url' => '']))->assertCreated();
});

it('is rate limited to five messages per hour per IP', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/app/v1/contact', platformContactPayload())->assertCreated();
    }

    $this->postJson('/api/app/v1/contact', platformContactPayload())
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After')
        ->assertJsonStructure(['details' => ['retry_after_seconds']]);

    expect(ContactMessage::query()->count())->toBe(5);
});
