<?php

namespace App\Enums;

enum TransactionDirection: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';
}
