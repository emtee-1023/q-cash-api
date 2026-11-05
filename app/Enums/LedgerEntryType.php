<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case PrincipalDisbursed = 'principal_disbursed';
    case FeeAssessed = 'fee_assessed';
    case Repayment = 'repayment';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}
