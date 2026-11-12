<?php

namespace App\Enums;

enum BusinessWithdrawalStatus: string
{
    case Processing = 'processing';
    case TimedOut = 'timed_out';
    case Successful = 'successful';
    case Failed = 'failed';
}
