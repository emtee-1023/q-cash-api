<?php

namespace App\Enums;

enum TransactionCategory: string
{
    case Sale = 'sale';
    case Refund = 'refund';
    case Reversal = 'reversal';
    case CapitalInjection = 'capital_injection';
    case InternalTransfer = 'internal_transfer';
    case NonRevenue = 'non_revenue';
    case Unknown = 'unknown';
}
