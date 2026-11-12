<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

final class MpesaTimestamp
{
    /**
     * M-Pesa sends timestamps in East Africa Time, while the application stores
     * timestamps in UTC.
     */
    public static function parse(string $value): Carbon
    {
        if ($value === '') {
            return now();
        }

        try {
            return Carbon::createFromFormat(
                'YmdHis',
                $value,
                (string) config('services.mpesa.timezone', 'Africa/Nairobi'),
            )->utc();
        } catch (Throwable) {
            return now();
        }
    }
}
