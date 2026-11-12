<?php

namespace App\Enums;

enum StkPushStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Timeout = 'timeout';
}
