<?php

namespace App\Enums;

enum AdvanceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Disbursing = 'disbursing';
    case Active = 'active';
    case Overdue = 'overdue';
    case Settled = 'settled';
    case Defaulted = 'defaulted';
    case Cancelled = 'cancelled';
}
