<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\Admin\AdminActivationService;
use App\Services\Auth\EmailOtpService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Admin Actions')]
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim($request->string('q')->toString());

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->withCount('businesses')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return AdminUserResource::collection($users);
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        EmailOtpService $emailOtpService,
    ): AdminUserResource {
        $attributes = [];

        foreach ([
            'name',
            'email',
            'phone_number',
            'id_type',
            'identification_number',
            'date_of_birth',
        ] as $field) {
            if ($request->has($field)) {
                $attributes[$field] = $request->input($field);
            }
        }

        $emailChanged = array_key_exists('email', $attributes)
            && $attributes['email'] !== $user->email;

        if ($emailChanged) {
            $attributes['email_verified_at'] = null;
        }

        $user->forceFill($attributes)->save();

        if ($emailChanged && $user->email !== null) {
            $emailOtpService->send($user);
        }

        return (new AdminUserResource($user->fresh()))
            ->additional(['message' => 'User updated.']);
    }

    public function grant(
        User $user,
        AdminActivationService $activationService,
    ): AdminUserResource|JsonResponse {
        if ($user->is_admin && $user->admin_activated_at !== null) {
            return response()->json([
                'message' => 'This user is already an activated admin.',
            ], 422);
        }

        if (blank($user->phone_number)) {
            return response()->json([
                'message' => 'This user has no phone number on file.',
            ], 422);
        }

        $activationService->grant($user);

        return (new AdminUserResource($user))
            ->additional(['message' => "Admin activation code sent to {$user->phone_number}."]);
    }

    public function revoke(
        Request $request,
        User $user,
        AdminActivationService $activationService,
    ): AdminUserResource|JsonResponse {
        if ($request->user()->is($user)) {
            return response()->json([
                'message' => 'You cannot revoke your own admin access.',
            ], 422);
        }

        $activationService->revoke($user);

        return (new AdminUserResource($user))
            ->additional(['message' => 'Admin access revoked.']);
    }
}
