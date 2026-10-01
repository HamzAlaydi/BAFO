<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Broadcasting\UnreadCountBroadcast;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\SampleNotifications;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\travel;

/*
 * In-app notifications API (API.md §1.8, resource §2.11).
 */

const NOTIFICATIONS_URL = '/api/app/v1/notifications';

/**
 * Stores an in-app notification for the user (database channel only, synchronously).
 */
function storeInAppNotification(User $user, NotificationType $type = NotificationType::CompetitionOpened): DatabaseNotification
{
    $class = $type->notificationClass();
    $notification = $class::fromPayload(SampleNotifications::payload($type));
    $user->notifyNow($notification, ['database']);

    return DatabaseNotification::query()->findOrFail($notification->id);
}

beforeEach(function () {
    $this->user = User::factory()->withMembership(null, OrgRole::Owner)->create();
    $this->other = User::factory()->withMembership(null, OrgRole::Owner)->create();
});

describe('GET /notifications', function () {
    it('lists the user\'s notifications newest first, rendered in the request language, with the unread count', function () {
        $older = storeInAppNotification($this->user, NotificationType::CompetitionInvited);
        travel(1)->minutes();
        $newer = storeInAppNotification($this->user, NotificationType::CompetitionFinalWindowStarted);
        $newer->markAsRead();
        storeInAppNotification($this->other);
        Sanctum::actingAs($this->user);

        $response = $this->getJson(NOTIFICATIONS_URL)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'type', 'title', 'body', 'subject' => ['type', 'id'], 'route', 'params', 'read_at', 'created_at']],
                'meta' => ['pagination' => ['type', 'current_page', 'per_page', 'has_more', 'total', 'last_page'], 'unread_count', 'server_time'],
            ])
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.type', 'competition.invited')
            ->assertJsonPath('data.1.title', 'دعوة للمشاركة في مناقصة')
            ->assertJsonPath('data.1.body', 'تدعوك شركة المصدر للمشاركة في «توريد أجهزة حاسب محمولة». رسوم المشاركة مغطّاة.')
            ->assertJsonPath('data.1.params.sponsored', true)
            ->assertJsonPath('data.1.read_at', null)
            ->assertJsonPath('data.1.route', '/competitions/01j9zq4m1x2a3b4c5d6e7f8g9h');

        expect($response->json('data.0.read_at'))->toBeIso8601Utc()
            ->and($response->json('data.0.created_at'))->toBeIso8601Utc()
            ->and($response->json('data.0.body'))->toBe('بدأت فترة التسعير النهائية في «توريد أجهزة حاسب محمولة»، وتُغلق المنافسة عند 9 نوفمبر 2026، 3:30 م.');

        $this->getJson(NOTIFICATIONS_URL, ['Accept-Language' => 'en'])
            ->assertJsonPath('data.1.title', 'New tender invitation')
            ->assertJsonPath('data.0.body', 'The final pricing window of “توريد أجهزة حاسب محمولة” has started. It closes at 9 Nov 2026, 3:30 PM.');
    });

    it('filters unread notifications and pages them', function () {
        foreach (range(1, 3) as $i) {
            storeInAppNotification($this->user);
        }
        storeInAppNotification($this->user)->markAsRead();
        Sanctum::actingAs($this->user);

        $this->getJson(NOTIFICATIONS_URL.'?unread=1&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.has_more', true)
            ->assertJsonPath('meta.unread_count', 3);
    });

    it('validates the query', function () {
        Sanctum::actingAs($this->user);

        $this->getJson(NOTIFICATIONS_URL.'?per_page=500&unread=maybe')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['per_page', 'unread']);
    });

    it('requires authentication', function () {
        $this->getJson(NOTIFICATIONS_URL)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });
});

it('returns the unread count', function () {
    storeInAppNotification($this->user);
    storeInAppNotification($this->user);
    storeInAppNotification($this->other);
    Sanctum::actingAs($this->user);

    $response = $this->getJson(NOTIFICATIONS_URL.'/unread-count')
        ->assertOk()
        ->assertJsonPath('data', ['unread_count' => 2]);

    expect(array_keys($response->json()))->toBe(['data', 'meta']);

    Auth::forgetGuards();
    $this->getJson(NOTIFICATIONS_URL.'/unread-count', ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
});

describe('POST /notifications/{notification}/read', function () {
    it('marks the notification read and broadcasts the new unread count', function () {
        Event::fake([UnreadCountBroadcast::class]);
        $notification = storeInAppNotification($this->user);
        storeInAppNotification($this->user);
        Sanctum::actingAs($this->user);

        $response = $this->postJson(NOTIFICATIONS_URL.'/'.strtoupper($notification->id).'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id);

        expect($response->json('data.read_at'))->toBeIso8601Utc()
            ->and($notification->fresh()?->read_at)->not->toBeNull();
        Event::assertDispatched(UnreadCountBroadcast::class, fn (UnreadCountBroadcast $e): bool => $e->userPublicId === $this->user->public_id && $e->unreadCount === 1);
    });

    it('keeps the first read time when read again, without broadcasting', function () {
        Event::fake([UnreadCountBroadcast::class]);
        $notification = storeInAppNotification($this->user);
        $notification->markAsRead();
        $readAt = $notification->fresh()?->read_at;
        travel(5)->minutes();
        Sanctum::actingAs($this->user);

        $this->postJson(NOTIFICATIONS_URL.'/'.$notification->id.'/read')->assertOk();

        expect($notification->fresh()?->read_at?->equalTo($readAt))->toBeTrue();
        Event::assertNotDispatched(UnreadCountBroadcast::class);
    });

    it('answers 404 for another user\'s notification and for unknown or malformed ids', function (string $id) {
        $theirs = storeInAppNotification($this->other);
        Sanctum::actingAs($this->user);

        $this->postJson(NOTIFICATIONS_URL.'/'.($id === 'theirs' ? $theirs->id : $id).'/read')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        expect($theirs->fresh()?->read_at)->toBeNull();
    })->with(['theirs', '01j9zq4m1x2a3b4c5d6e7f8g9h', 'not-a-ulid']);
});

it('marks every notification read and broadcasts zero', function () {
    Event::fake([UnreadCountBroadcast::class]);
    storeInAppNotification($this->user);
    storeInAppNotification($this->user);
    $theirs = storeInAppNotification($this->other);
    Sanctum::actingAs($this->user);

    $this->postJson(NOTIFICATIONS_URL.'/read-all')
        ->assertOk()
        ->assertJsonPath('data', ['unread_count' => 0]);

    expect($this->user->unreadNotifications()->count())->toBe(0)
        ->and($theirs->fresh()?->read_at)->toBeNull();
    Event::assertDispatched(UnreadCountBroadcast::class, fn (UnreadCountBroadcast $e): bool => $e->unreadCount === 0);
});

describe('DELETE', function () {
    it('deletes one notification of the user', function () {
        Event::fake([UnreadCountBroadcast::class]);
        $notification = storeInAppNotification($this->user);
        storeInAppNotification($this->user);
        Sanctum::actingAs($this->user);

        $this->deleteJson(NOTIFICATIONS_URL.'/'.$notification->id)->assertNoContent();

        expect(DatabaseNotification::query()->find($notification->id))->toBeNull();
        Event::assertDispatched(UnreadCountBroadcast::class, fn (UnreadCountBroadcast $e): bool => $e->unreadCount === 1);
    });

    it('does not delete another user\'s notification', function () {
        $theirs = storeInAppNotification($this->other);
        Sanctum::actingAs($this->user);

        $this->deleteJson(NOTIFICATIONS_URL.'/'.$theirs->id)->assertNotFound();

        expect(DatabaseNotification::query()->find($theirs->id))->not->toBeNull();
    });

    it('clears the user\'s inbox only', function () {
        Event::fake([UnreadCountBroadcast::class]);
        storeInAppNotification($this->user);
        storeInAppNotification($this->user);
        $theirs = storeInAppNotification($this->other);
        Sanctum::actingAs($this->user);

        $this->deleteJson(NOTIFICATIONS_URL)->assertNoContent();

        expect($this->user->notifications()->count())->toBe(0)
            ->and(DatabaseNotification::query()->find($theirs->id))->not->toBeNull();
        Event::assertDispatched(UnreadCountBroadcast::class, fn (UnreadCountBroadcast $e): bool => $e->unreadCount === 0);
    });

    it('requires authentication', function () {
        $this->deleteJson(NOTIFICATIONS_URL)->assertUnauthorized();
    });
});
