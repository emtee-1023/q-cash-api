<?php

namespace App\Enums;

enum ModelType: string
{
    case RevenueRegressor = 'revenue_regressor';
    case TransactionClassifier = 'transaction_classifier';
}
