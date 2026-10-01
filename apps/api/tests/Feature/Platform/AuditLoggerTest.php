<?php

declare(strict_types=1);

use App\Modules\Platform\Models\ContactMessage;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use App\Support\Auth\ActorType;
use App\Support\Auth\CurrentActor;
use App\Support\Files\File;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\Platform\TestUser;

function platformUserActor(int $id = 4, ?int $organizationId = 11): Actor
{
    $request = Request::create('/api/app/v1/x', 'POST', server: ['HTTP_X_PLATFORM' => 'ios', 'HTTP_USER_AGENT' => 'BafoApp/2.0', 'REMOTE_ADDR' => '10.0.0.7']);
    $request->attributes->set('request_id', 'req-12345678');

    return Actor::forUser(TestUser::make($id, $organizationId, 'سارة'), $request);
}

it('writes an entry with the actor, subject and request context', function () {
    $this->freezeTime();
    $message = ContactMessage::factory()->create();

    AuditLogger::log('contact_message.updated', $message, ['status' => ['from' => 'new', 'to' => 'read']], ['note' => 'x'], platformUserActor());

    $entry = AuditLog::query()->sole();

    expect($entry->action)->toBe('contact_message.updated')
        ->and($entry->occurred_at->equalTo(now()))->toBeTrue()
        ->and($entry->organization_id)->toBe(11)
        ->and($entry->actor_type)->toBe(ActorType::User)
        ->and($entry->actor_id)->toBe(4)
        ->and($entry->actor_label)->toBe('سارة')
        ->and($entry->subject_type)->toBe('contact_message')
        ->and($entry->subject_id)->toBe($message->id)
        ->and($entry->subject_public_id)->toBe($message->public_id)
        ->and($entry->changes)->toEqual(['status' => ['from' => 'new', 'to' => 'read']])
        ->and($entry->meta)->toBe(['note' => 'x'])
        ->and($entry->channel)->toBe('ios')
        ->and($entry->ip)->toBe('10.0.0.7')
        ->and($entry->user_agent)->toBe('BafoApp/2.0')
        ->and($entry->request_id)->toBe('req-12345678');
});

it('defaults to the current actor, and to the system actor outside requests', function () {
    AuditLogger::log('platform.checked');

    expect(AuditLog::query()->sole())
        ->actor_type->toBe(ActorType::System)
        ->actor_label->toBe('system')
        ->channel->toBe('system')
        ->subject_type->toBeNull()
        ->changes->toBeNull()
        ->meta->toBeNull();

    CurrentActor::set(platformUserActor(id: 8));
    AuditLogger::log('platform.checked');

    expect(AuditLog::query()->latest('id')->first()?->actor_id)->toBe(8);
});

it('takes the organization from the subject when the actor has none', function () {
    $file = File::factory()->create(['organization_id' => 33]);

    AuditLogger::log('file.deleted', $file, actor: Actor::system());
    AuditLogger::log('file.deleted', $file, actor: platformUserActor(organizationId: 11));
    AuditLogger::log('file.deleted', $file, actor: platformUserActor(organizationId: 11), organizationId: 44);

    expect(AuditLog::query()->orderBy('id')->pluck('organization_id')->all())->toBe([33, 11, 44])
        ->and(AuditLog::query()->first()?->subject_type)->toBe('file');
});

it('redacts secrets at any depth', function () {
    AuditLogger::log('api_key.created', null, [
        'password' => ['from' => 'old', 'to' => 'new'],
        'name' => ['from' => 'a', 'to' => 'b'],
    ], [
        'secret' => 'whsec_abc',
        'nested' => ['api_key' => 'bafo_test_xyz', 'label' => 'ok'],
        'token_hash' => 'abc',
    ]);

    $entry = AuditLog::query()->sole();

    // jsonb orders object keys by length: compare by value.
    expect($entry->changes)->toEqual([
        'password' => ['from' => '[redacted]', 'to' => '[redacted]'],
        'name' => ['from' => 'a', 'to' => 'b'],
    ])->and($entry->meta)->toEqual([
        'secret' => '[redacted]',
        'nested' => ['api_key' => '[redacted]', 'label' => 'ok'],
        'token_hash' => '[redacted]',
    ]);
});

it('builds a from/to diff of the last save', function () {
    $message = ContactMessage::factory()->create(['subject' => 'Old', 'company' => 'A']);
    $message->update(['subject' => 'New', 'company' => 'B']);

    expect(AuditLogger::diff($message))->toEqual([
        'subject' => ['from' => 'Old', 'to' => 'New'],
        'company' => ['from' => 'A', 'to' => 'B'],
    ])->and(AuditLogger::diff($message, ['subject']))->toBe([
        'subject' => ['from' => 'Old', 'to' => 'New'],
    ]);
});

it('is append-only in the database', function (string $statement) {
    AuditLogger::log('platform.checked');

    DB::statement($statement);
})->with([
    'update' => "update audit_logs set action = 'x'",
    'delete' => 'delete from audit_logs',
])->throws(QueryException::class, 'append-only');
