<?php

namespace App\Http\Controllers\Api;

use App\Enums\BusinessUserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddBusinessMemberRequest;
use App\Http\Resources\BusinessInvitationResource;
use App\Http\Resources\BusinessMemberResource;
use App\Models\Business;
use App\Services\Businesses\BusinessMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Business Members
 */
class BusinessMemberController extends Controller
{
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->authorize('manageMembers', $business);

        return BusinessMemberResource::collection(
            $business->businessUsers()->with('user')->get(),
        );
    }

    public function store(
        AddBusinessMemberRequest $request,
        Business $business,
        BusinessMembershipService $membershipService,
    ): JsonResponse {
        $this->authorize('manageMembers', $business);

        $result = $membershipService->add(
            business: $business,
            inviter: $request->user(),
            phoneNumber: $request->string('phone_number')->toString(),
            role: BusinessUserRole::from($request->string('role')->toString()),
            name: $request->input('name'),
        );

        if ($result['type'] === 'member') {
            return response()->json([
                'message' => 'User added to the business.',
                'type' => 'member',
                'data' => new BusinessMemberResource($result['member']),
            ], 201);
        }

        return response()->json([
            'message' => 'Invitation sent.',
            'type' => 'invitation',
            'data' => new BusinessInvitationResource($result['invitation']),
        ], 201);
    }
}
