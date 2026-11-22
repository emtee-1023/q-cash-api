<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function paymentNotificationFor(User $user): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'payment_received',
        'data' => [
            'type' => 'payment_received',
            'title' => 'Payment received',
            'body' => 'A payment was received.',
        ],
        'read_at' => null,
    ]);
}

test('the notification index requires authentication', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});

test('a user only sees their own notifications newest first', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $first = paymentNotificationFor($user);
    $first->forceFill(['created_at' => now()->subMinute()])->save();

    $second = paymentNotificationFor($user);
    $otherNotification = paymentNotificationFor($otherUser);

    Sanctum::actingAs($user);

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.1.id', $first->id)
        ->assertJsonPath('data.0.type', 'payment_received')
        ->assertJsonMissing(['id' => $otherNotification->id]);
});

test('the notification index can return only unread notifications', function () {
    $user = User::factory()->create();

    $read = paymentNotificationFor($user);
    $read->markAsRead();

    $unread = paymentNotificationFor($user);

    Sanctum::actingAs($user);

    $this->getJson('/api/notifications?unread=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $unread->id);
});

test('the unread count reflects only unread notifications', function () {
    $user = User::factory()->create();

    paymentNotificationFor($user);
    paymentNotificationFor($user);
    paymentNotificationFor($user)->markAsRead();
    paymentNotificationFor(User::factory()->create());

    Sanctum::actingAs($user);

    $this->getJson('/api/notifications/unread-count')
        ->assertOk()
        ->assertExactJson(['unread_count' => 2]);
});

test('a notification can be marked as read', function () {
    $user = User::factory()->create();
    $notification = paymentNotificationFor($user);

    Sanctum::actingAs($user);

    $this->postJson("/api/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id)
        ->assertJsonPath('data.read_at', $notification->fresh()->read_at->toJSON());

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification as read', function () {
    $user = User::factory()->create();
    $otherNotification = paymentNotificationFor(User::factory()->create());

    Sanctum::actingAs($user);

    $this->postJson("/api/notifications/{$otherNotification->id}/read")
        ->assertNotFound();

    expect($otherNotification->fresh()->read_at)->toBeNull();
});

test('all notifications can be marked as read', function () {
    $user = User::factory()->create();

    paymentNotificationFor($user);
    paymentNotificationFor($user);

    Sanctum::actingAs($user);

    $this->postJson('/api/notifications/read-all')
        ->assertOk()
        ->assertExactJson([
            'message' => 'Notifications marked as read.',
            'marked_read' => 2,
        ]);

    expect($user->unreadNotifications()->count())->toBe(0);
});
