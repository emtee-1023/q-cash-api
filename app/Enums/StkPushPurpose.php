<?php

namespace App\Enums;

enum StkPushPurpose: string
{
    case Payment = 'payment';
    case Repayment = 'repayment';
}
