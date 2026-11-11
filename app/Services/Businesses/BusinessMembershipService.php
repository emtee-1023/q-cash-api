<?php

namespace App\Services\Businesses;

use App\Enums\BusinessUserRole;
use App\Enums\BusinessUserStatus;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessUser;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class BusinessMembershipService
{
    public function __construct(private BusinessInvitationOtpService $otpService) {}

    /**
     * @return array{type: 'member', member: BusinessUser}|array{type: 'invitation', invitation: BusinessInvitation}
     */
    public function add(
        Business $business,
        User $inviter,
        string $phoneNumber,
        BusinessUserRole $role,
        ?string $name = null,
    ): array {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);
        $user = User::query()->where('phone_number', $phoneNumber)->first();

        if ($user !== null) {
            if ($business->businessUsers()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'phone_number' => 'This user is already a member of the business.',
                ]);
            }

            $member = $business->businessUsers()->create([
                'user_id' => $user->id,
                'role' => $role->value,
                'is_primary_contact' => false,
                'status' => BusinessUserStatus::Active->value,
            ]);

            BusinessInvitation::query()
                ->where('business_id', $business->id)
                ->where('phone_number', $phoneNumber)
                ->whereNull('accepted_at')
                ->update([
                    'user_id' => $user->id,
                    'accepted_at' => now(),
                ]);

            return [
                'type' => 'member',
                'member' => $member->load('user'),
            ];
        }

        $invitation = BusinessInvitation::query()->updateOrCreate(
            [
                'business_id' => $business->id,
                'phone_number' => $phoneNumber,
            ],
            [
                'invited_by_user_id' => $inviter->id,
                'user_id' => null,
                'name' => $name,
                'role' => $role->value,
                'accepted_at' => null,
            ],
        );

        $this->otpService->send($phoneNumber);

        return [
            'type' => 'invitation',
            'invitation' => $invitation,
        ];
    }

    public function resendOtp(string $phoneNumber): void
    {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);

        $hasPendingInvitation = BusinessInvitation::query()
            ->where('phone_number', $phoneNumber)
            ->whereNull('accepted_at')
            ->exists();

        if ($hasPendingInvitation) {
            $this->otpService->send($phoneNumber);
        }
    }

    /**
     * @return array{user: User, businesses: Collection<int, Business>}
     */
    public function activate(
        string $phoneNumber,
        string $otp,
        string $password,
        ?string $name = null,
    ): array {
        $phoneNumber = PhoneNumber::normalize($phoneNumber);

        $invitations = BusinessInvitation::query()
            ->where('phone_number', $phoneNumber)
            ->whereNull('accepted_at')
            ->with('business')
            ->get();

        if ($invitations->isEmpty()) {
            throw ValidationException::withMessages([
                'phone_number' => 'No pending invitation was found for this phone number.',
            ]);
        }

        if (! $this->otpService->verify($phoneNumber, $otp)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid or expired invitation code.',
            ]);
        }

        return DB::transaction(function () use ($invitations, $phoneNumber, $password, $name): array {
            $user = User::query()->where('phone_number', $phoneNumber)->first();

            if ($user === null) {
                $user = User::create([
                    'name' => $name ?? $invitations->first()->name ?? $phoneNumber,
                    'phone_number' => $phoneNumber,
                    'phone_verified_at' => now(),
                    'password' => Hash::make($password),
                ]);
            } else {
                $user->forceFill([
                    'phone_verified_at' => $user->phone_verified_at ?? now(),
                ])->save();
            }

            foreach ($invitations as $invitation) {
                if (! $invitation->business->businessUsers()->where('user_id', $user->id)->exists()) {
                    $invitation->business->businessUsers()->create([
                        'user_id' => $user->id,
                        'role' => $invitation->role->value,
                        'is_primary_contact' => false,
                        'status' => BusinessUserStatus::Active->value,
                    ]);
                }

                $invitation->forceFill([
                    'user_id' => $user->id,
                    'accepted_at' => now(),
                ])->save();
            }

            return [
                'user' => $user->refresh(),
                'businesses' => $invitations->pluck('business'),
            ];
        });
    }
}
