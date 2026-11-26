<?php

namespace App\Services\Disbursements;

use App\Models\Advance;
use App\Models\Disbursement;
use App\Services\Advances\AdvanceService;
use App\Services\Notifications\BusinessNotifier;
use App\Services\Sms\DisbursementConfirmationSms;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Support\Facades\DB;

class WalletDisbursementService
{
    public function __construct(
        private readonly AdvanceService $advances,
        private readonly BusinessWalletService $wallets,
        private readonly BusinessNotifier $notifier,
        private readonly DisbursementConfirmationSms $confirmationSms,
    ) {}

    /**
     * Credit the advance principal to the business QCash wallet instead of
     * sending it out over M-Pesa B2C.
     */
    public function disburse(Advance $advance): Disbursement
    {
        $disbursement = DB::transaction(function () use ($advance) {
            $disbursement = $this->advances->requestDisbursement($advance, 'qcash_wallet');

            $this->wallets->credit(
                $advance->business,
                $advance->principal_amount_minor,
                description: 'Cash advance disbursement',
                reference: 'advance:'.$advance->id,
            );

            return $this->advances->markDisbursementSuccessful(
                $disbursement,
                'wallet:'.$advance->id,
                ['credited_to_wallet' => true],
            );
        });

        $this->confirmationSms->send($advance->business, $disbursement);
        $this->notifier->advanceDisbursed($advance->business, $disbursement);

        return $disbursement->refresh();
    }
}
