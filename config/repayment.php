<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Revenue Deduction Repayment
    |--------------------------------------------------------------------------
    |
    | The primary repayment mechanism deducts a percentage of each eligible
    | incoming payment while an advance is outstanding. All amounts are minor
    | units. The working capital floor preserves at least this share of every
    | receipt, which caps the effective deduction rate.
    |
    */

    'daily_cap_minor' => (int) env('REPAYMENT_DAILY_CAP_MINOR', 400000),

    'working_capital_floor_bps' => (int) env('REPAYMENT_WORKING_CAPITAL_FLOOR_BPS', 8500),

    /*
    |--------------------------------------------------------------------------
    | Access Fee
    |--------------------------------------------------------------------------
    |
    | Charged on the requested advance amount. The borrower receives the
    | requested amount less this fee and repays the full requested amount.
    |
    */

    'access_fee_bps' => (int) env('ACCESS_FEE_BPS', 200),

    /*
    |--------------------------------------------------------------------------
    | Repayment Window
    |--------------------------------------------------------------------------
    |
    | The maximum number of days a new advance can run before it falls due.
    | Assessment recommendations are capped at this value.
    |
    */

    'maximum_days' => (int) env('REPAYMENT_MAXIMUM_DAYS', 30),

];
