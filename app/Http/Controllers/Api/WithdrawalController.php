<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientWalletBalanceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWithdrawalRequest;
use App\Http\Resources\BusinessWithdrawalResource;
use App\Models\Business;
use App\Services\Withdrawals\MpesaB2cWithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @tags Withdrawals
 */
class WithdrawalController extends Controller
{
    /**
     * List withdrawals
     *
     * Returns a paginated list of withdrawals for the business, newest first.
     */
    public function index(Request $request, Business $business)
    {
        $this->authorize('view', $business);

        $withdrawals = $business->withdrawals()
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return BusinessWithdrawalResource::collection($withdrawals);
    }

    /**
     * Withdraw to the owner's M-Pesa
     *
     * Debits the business wallet and sends the amount to the business owner's
     * phone number via M-Pesa B2C.
     */
    public function store(
        StoreWithdrawalRequest $request,
        Business $business,
        MpesaB2cWithdrawalService $withdrawals,
    ) {
        $this->authorize('manageFinance', $business);

        try {
            $withdrawal = $withdrawals->withdraw(
                $business,
                $request->integer('amount_minor'),
            );
        } catch (InsufficientWalletBalanceException $exception) {
            throw ValidationException::withMessages([
                'amount_minor' => $exception->getMessage(),
            ]);
        }

        return (new BusinessWithdrawalResource($withdrawal))
            ->additional(['message' => 'Withdrawal requested.'])
            ->response()
            ->setStatusCode(201);
    }
}
