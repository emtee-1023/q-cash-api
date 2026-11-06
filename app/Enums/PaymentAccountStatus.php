<?php

namespace App\Enums;

enum PaymentAccountStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
