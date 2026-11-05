<?php

namespace App\Enums;

enum CollectionMethod: string
{
    case RevenueDeduction = 'revenue_deduction';
    case ManualPayment = 'manual_payment';
    case Fallback = 'fallback';
}
