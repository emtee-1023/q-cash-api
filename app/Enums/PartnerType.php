<?php

namespace App\Enums;

enum PartnerType: string
{
    case Bank = 'bank';
    case FinancialInstitution = 'financial_institution';
    case Other = 'other';
}
