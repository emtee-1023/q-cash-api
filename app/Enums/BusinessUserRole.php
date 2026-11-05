<?php

namespace App\Enums;

enum BusinessUserRole: string
{
    case Owner = 'owner';
    case Administrator = 'administrator';
    case Staff = 'staff';
}
