<?php

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Markt\LaravelAuth\Contracts\SmsSender;
use Tests\Fixtures\FakeSmsSender;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance(SmsSender::class, new FakeSmsSender);
});

function addTestBusinessMember(Business $business, User $user, string $role = 'owner', string $status = 'active'): BusinessUser
{
    return $business->businessUsers()->create([
        'user_id' => $user->id,
        'role' => $role,
        'is_primary_contact' => $role === 'owner',
        'status' => $status,
    ]);
}

function fakeSms(): FakeSmsSender
{
    $sms = app(SmsSender::class);

    if (! $sms instanceof FakeSmsSender) {
        throw new RuntimeException('Expected fake SMS sender.');
    }

    return $sms;
}

function latestBusinessInvitationOtp(string $phoneNumber): string
{
    $message = fakeSms()->lastMessageFor($phoneNumber);

    if ($message === null || ! preg_match('/(\d{6})/', $message, $matches)) {
        throw new RuntimeException('No invitation OTP was sent.');
    }

    return $matches[1];
}

test('an owner can add an existing user as a business administrator', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    $member = User::factory()->create(['phone_number' => '254712345678']);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => '0712345678',
        'role' => BusinessUserRole::Administrator->value,
    ])->assertCreated()
        ->assertJsonPath('type', 'member')
        ->assertJsonPath('message', 'User added to the business.')
        ->assertJsonPath('data.user_id', $member->id)
        ->assertJsonPath('data.role', BusinessUserRole::Administrator->value);

    expect($business->businessUsers()
        ->where('user_id', $member->id)
        ->where('role', BusinessUserRole::Administrator->value)
        ->where('status', BusinessUserStatus::Active->value)
        ->exists())->toBeTrue();

    expect(fakeSms()->lastMessageFor($member->phone_number))->toBeNull();
});

test('a business administrator can add an existing user as staff', function () {
    $administrator = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $administrator, BusinessUserRole::Administrator->value);

    $member = User::factory()->create(['phone_number' => '254712345679']);

    Sanctum::actingAs($administrator);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => '0712345679',
        'role' => BusinessUserRole::Staff->value,
    ])->assertCreated()
        ->assertJsonPath('data.role', BusinessUserRole::Staff->value);

    expect($business->businessUsers()->where('user_id', $member->id)->exists())->toBeTrue();
});

test('staff cannot add business members', function () {
    $staff = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $staff, BusinessUserRole::Staff->value);

    Sanctum::actingAs($staff);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => '0712345678',
        'role' => BusinessUserRole::Staff->value,
    ])->assertForbidden();
});

test('non members cannot add business members', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => '0712345678',
        'role' => BusinessUserRole::Staff->value,
    ])->assertForbidden();
});

test('suspended business administrators cannot add members', function () {
    $administrator = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $administrator, BusinessUserRole::Administrator->value, 'suspended');

    Sanctum::actingAs($administrator);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => '0712345678',
        'role' => BusinessUserRole::Staff->value,
    ])->assertForbidden();
});

test('an existing member cannot be added twice', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    $member = User::factory()->create(['phone_number' => '254712345678']);
    addTestBusinessMember($business, $member, BusinessUserRole::Staff->value);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => $member->phone_number,
        'role' => BusinessUserRole::Staff->value,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('phone_number');
});

test('the owner role cannot be assigned through member invites', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => '0712345678',
        'role' => BusinessUserRole::Owner->value,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('role');
});

test('adding a non user creates an invitation and sends an otp', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    $phoneNumber = '254712345678';

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => '0712345678',
        'role' => BusinessUserRole::Staff->value,
        'name' => 'Invited Person',
    ])->assertCreated()
        ->assertJsonPath('type', 'invitation')
        ->assertJsonPath('message', 'Invitation sent.')
        ->assertJsonPath('data.phone_number', $phoneNumber)
        ->assertJsonPath('data.role', BusinessUserRole::Staff->value);

    expect(User::query()->where('phone_number', $phoneNumber)->exists())->toBeFalse();
    expect(BusinessInvitation::query()
        ->where('business_id', $business->id)
        ->where('phone_number', $phoneNumber)
        ->whereNull('accepted_at')
        ->exists())->toBeTrue();

    expect(fakeSms()->lastMessageFor($phoneNumber))->toContain('invited');
});

test('an invited user can activate their account and join the business', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    $phoneNumber = '254712345678';

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => $phoneNumber,
        'role' => BusinessUserRole::Administrator->value,
        'name' => 'Invited Person',
    ])->assertCreated();

    $otp = latestBusinessInvitationOtp($phoneNumber);

    $this->postJson('/api/business-invitations/activate', [
        'phone_number' => $phoneNumber,
        'otp' => $otp,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated()
        ->assertJsonStructure(['token', 'token_type', 'user'])
        ->assertJsonPath('user.phone_number', $phoneNumber)
        ->assertJsonPath('user.name', 'Invited Person');

    $user = User::query()->where('phone_number', $phoneNumber)->firstOrFail();
    $member = $business->businessUsers()->where('user_id', $user->id)->firstOrFail();
    $invitation = BusinessInvitation::query()
        ->where('business_id', $business->id)
        ->where('phone_number', $phoneNumber)
        ->firstOrFail();

    expect($user->phone_verified_at)->not->toBeNull();
    expect(Hash::check('password123', $user->password))->toBeTrue();
    expect($member->role)->toBe(BusinessUserRole::Administrator);
    expect($member->status)->toBe(BusinessUserStatus::Active);
    expect($invitation->accepted_at)->not->toBeNull();
    expect($invitation->user_id)->toBe($user->id);
});

test('invitation activation rejects an invalid otp', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    $phoneNumber = '254712345678';

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => $phoneNumber,
        'role' => BusinessUserRole::Staff->value,
    ])->assertCreated();

    $this->postJson('/api/business-invitations/activate', [
        'phone_number' => $phoneNumber,
        'otp' => '000000',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('otp');

    expect(User::query()->where('phone_number', $phoneNumber)->exists())->toBeFalse();
});

test('activation requires a pending invitation', function () {
    $this->postJson('/api/business-invitations/activate', [
        'phone_number' => '254712345678',
        'otp' => '123456',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('phone_number');
});

test('an invitation otp can be resent and invalidates the old code', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    $phoneNumber = '254712345678';

    Sanctum::actingAs($owner);

    $this->postJson("/api/businesses/{$business->id}/members", [
        'phone_number' => $phoneNumber,
        'role' => BusinessUserRole::Staff->value,
    ])->assertCreated();

    $oldOtp = latestBusinessInvitationOtp($phoneNumber);

    $this->postJson('/api/business-invitations/resend-otp', [
        'phone_number' => $phoneNumber,
    ])->assertOk();

    $newOtp = latestBusinessInvitationOtp($phoneNumber);

    $this->postJson('/api/business-invitations/activate', [
        'phone_number' => $phoneNumber,
        'otp' => $oldOtp,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('otp');

    $this->postJson('/api/business-invitations/activate', [
        'phone_number' => $phoneNumber,
        'otp' => $newOtp,
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();
});

test('resending an invitation otp is generic when no invitation exists', function () {
    $phoneNumber = '254712345678';

    $this->postJson('/api/business-invitations/resend-otp', [
        'phone_number' => $phoneNumber,
    ])->assertOk()
        ->assertJsonPath('message', 'If an invitation exists, a new verification code has been sent.');

    expect(fakeSms()->lastMessageFor($phoneNumber))->toBeNull();
});

test('owners and administrators can list members but staff cannot', function () {
    $owner = User::factory()->create();
    $business = Business::factory()->create();
    addTestBusinessMember($business, $owner);

    $administrator = User::factory()->create();
    addTestBusinessMember($business, $administrator, BusinessUserRole::Administrator->value);

    $staff = User::factory()->create();
    addTestBusinessMember($business, $staff, BusinessUserRole::Staff->value);

    Sanctum::actingAs($owner);

    $this->getJson("/api/businesses/{$business->id}/members")
        ->assertOk()
        ->assertJsonFragment(['user_id' => $staff->id]);

    Sanctum::actingAs($administrator);

    $this->getJson("/api/businesses/{$business->id}/members")
        ->assertOk();

    Sanctum::actingAs($staff);

    $this->getJson("/api/businesses/{$business->id}/members")
        ->assertForbidden();
});
