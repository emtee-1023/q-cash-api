<?php

namespace App\Enums;

enum BusinessUserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
