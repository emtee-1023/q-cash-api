<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';
}
