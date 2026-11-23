<?php

namespace App\Http\Resources;

use App\Models\Advance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array{
 *     today_earnings_minor: int,
 *     last_month_earnings_minor: int,
 *     outstanding_balance_minor: int,
 *     latest_advance: ?Advance,
 *     available_balance_minor: int,
 *     available_advance_minor: int,
 * }
 */
class BusinessAnalyticsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'today_earnings_minor' => $this['today_earnings_minor'],
            'last_month_earnings_minor' => $this['last_month_earnings_minor'],
            'outstanding_balance_minor' => $this['outstanding_balance_minor'],
            'latest_advance' => $this['latest_advance'] === null
                ? null
                : new AdvanceResource($this['latest_advance']),
            'available_balance_minor' => $this['available_balance_minor'],
            'available_advance_minor' => $this['available_advance_minor'],
        ];
    }
}
