<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessKycStatus;
use App\Enums\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBusinessKycStatusRequest;
use App\Http\Requests\Admin\UpdateBusinessStatusRequest;
use App\Http\Resources\AdminBusinessDetailResource;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Admin Actions')]
class BusinessController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $kycStatus = $request->string('kyc_status')->toString();

        $businesses = Business::query()
            ->with('businessType')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('business_name', 'like', "%{$search}%")
                        ->orWhere('registration_number', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%")
                        ->orWhere('collection_account_number', 'like', "%{$search}%");
                });
            })
            ->when(
                BusinessStatus::tryFrom($status),
                fn ($query, BusinessStatus $status) => $query->where('status', $status->value),
            )
            ->when(
                BusinessKycStatus::tryFrom($kycStatus),
                fn ($query, BusinessKycStatus $kycStatus) => $query->where('kyc_status', $kycStatus->value),
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return BusinessResource::collection($businesses);
    }

    public function show(Business $business): AdminBusinessDetailResource
    {
        return new AdminBusinessDetailResource($this->loadBusiness($business));
    }

    public function updateStatus(
        UpdateBusinessStatusRequest $request,
        Business $business,
    ): AdminBusinessDetailResource {
        $business->update([
            'status' => $request->string('status')->toString(),
        ]);

        return (new AdminBusinessDetailResource($this->loadBusiness($business)))
            ->additional(['message' => 'Business status updated.']);
    }

    public function updateKycStatus(
        UpdateBusinessKycStatusRequest $request,
        Business $business,
    ): AdminBusinessDetailResource {
        $business->update([
            'kyc_status' => $request->string('kyc_status')->toString(),
        ]);

        return (new AdminBusinessDetailResource($this->loadBusiness($business)))
            ->additional(['message' => 'KYC status updated.']);
    }

    private function loadBusiness(Business $business): Business
    {
        return $business->load([
            'businessType',
            'businessUsers.user',
            'paymentAccounts',
        ]);
    }
}
