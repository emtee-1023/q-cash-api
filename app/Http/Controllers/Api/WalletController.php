<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessWalletResource;
use App\Models\Business;
use App\Services\Wallets\BusinessWalletService;

/**
 * @tags Wallet
 */
class WalletController extends Controller
{
    /**
     * Show the business wallet
     *
     * Returns the available balance held by QCash for the business.
     */
    public function show(Business $business, BusinessWalletService $wallets): BusinessWalletResource
    {
        $this->authorize('view', $business);

        return new BusinessWalletResource($wallets->walletFor($business));
    }
}
