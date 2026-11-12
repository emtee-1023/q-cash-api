<?php

namespace App\Enums;

enum BusinessWalletEntryType: string
{
    case Credit = 'credit';
    case Debit = 'debit';
    case Refund = 'refund';
}
