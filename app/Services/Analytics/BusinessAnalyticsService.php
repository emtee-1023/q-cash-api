<?php

namespace App\Services\Analytics;

use App\Enums\TransactionDirection;
use App\Models\Advance;
use App\Models\Business;
use App\Services\Wallets\BusinessWalletService;
use Illuminate\Support\Carbon;

class BusinessAnalyticsService
{
    public function __construct(private readonly BusinessWalletService $wallets) {}

    /**
     * @return array{
     *     today_earnings_minor: int,
     *     last_month_earnings_minor: int,
     *     outstanding_balance_minor: int,
     *     latest_advance: ?Advance,
     *     available_balance_minor: int,
     *     available_advance_minor: int,
     * }
     */
    public function for(Business $business): array
    {
        $now = now();

        return [
            'today_earnings_minor' => $this->inflowBetween(
                $business,
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
            ),
            'last_month_earnings_minor' => $this->inflowBetween(
                $business,
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ),
            'outstanding_balance_minor' => (int) $business->advances()->sum('outstanding_balance_minor'),
            'latest_advance' => $business->advances()
                ->latest()
                ->orderByDesc('id')
                ->first(),
            'available_balance_minor' => $this->wallets->balance($business),
            'available_advance_minor' => (int) ($business->assessments()
                ->latest('assessment_date')
                ->orderByDesc('id')
                ->value('recommended_advance_amount_minor') ?? 0),
        ];
    }

    private function inflowBetween(Business $business, Carbon $start, Carbon $end): int
    {
        return (int) $business->transactions()
            ->where('direction', TransactionDirection::Inflow->value)
            ->whereBetween('transaction_time', [$start, $end])
            ->sum('amount_minor');
    }
}
