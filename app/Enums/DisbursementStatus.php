<?php

namespace App\Enums;

enum DisbursementStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Successful = 'successful';
    case Failed = 'failed';
    case Reversed = 'reversed';
}
