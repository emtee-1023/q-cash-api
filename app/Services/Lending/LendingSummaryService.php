<?php

namespace App\Services\Lending;

use App\Enums\AdvanceStatus;
use App\Models\Advance;
use App\Models\Business;
use Illuminate\Support\Collection;

class LendingSummaryService
{
    /**
     * Annualised APR in basis points using the contractual repayment window.
     *
     * APR% = (fee / principal) x (365 / days) x 100. Because the borrower can
     * take longer than the contractual window to settle, treat this as the
     * minimum possible APR rather than a realised rate.
     */
    public function aprBps(Advance $advance): int
    {
        $principal = (int) $advance->principal_amount_minor;
        $days = (int) $advance->maximum_repayment_days;
        $fee = (int) $advance->fee_amount_minor;

        if ($principal <= 0 || $days <= 0) {
            return 0;
        }

        return (int) round(($fee / $principal) * (365 / $days) * 10000);
    }

    /**
     * @param  Collection<int, Advance>  $advances
     * @return array<string, int>
     */
    public function summarize(Collection $advances): array
    {
        $summary = [
            'loans_count' => $advances->count(),
            'disbursed_loans_count' => 0,
            'active_loans_count' => 0,
            'overdue_loans_count' => 0,
            'settled_loans_count' => 0,
            'defaulted_loans_count' => 0,
            'principal_minor' => 0,
            'disbursed_principal_minor' => 0,
            'fees_minor' => 0,
            'total_repayable_minor' => 0,
            'repaid_minor' => 0,
            'fee_repaid_minor' => 0,
            'principal_repaid_minor' => 0,
            'outstanding_minor' => 0,
            'average_apr_bps' => 0,
            'weighted_apr_bps' => 0,
        ];

        $aprTotal = 0;
        $aprLoans = 0;
        $weightedNumerator = 0;
        $weightedPrincipal = 0;

        foreach ($advances as $advance) {
            $status = $advance->status;

            $summary['principal_minor'] += (int) $advance->principal_amount_minor;
            $summary['fees_minor'] += (int) $advance->fee_amount_minor;
            $summary['total_repayable_minor'] += (int) $advance->total_repayable_minor;
            $summary['repaid_minor'] += (int) $advance->amount_repaid_minor;
            $summary['fee_repaid_minor'] += (int) $advance->fee_repaid_minor;
            $summary['principal_repaid_minor'] += (int) $advance->principal_repaid_minor;

            if ($advance->disbursed_at !== null) {
                $summary['disbursed_loans_count']++;
                $summary['disbursed_principal_minor'] += (int) $advance->principal_amount_minor;
            }

            if (in_array($status, [
                AdvanceStatus::Active,
                AdvanceStatus::Overdue,
                AdvanceStatus::Defaulted,
            ], true)) {
                $summary['outstanding_minor'] += (int) $advance->outstanding_balance_minor;
            }

            match ($status) {
                AdvanceStatus::Active => $summary['active_loans_count']++,
                AdvanceStatus::Overdue => $summary['overdue_loans_count']++,
                AdvanceStatus::Settled => $summary['settled_loans_count']++,
                AdvanceStatus::Defaulted => $summary['defaulted_loans_count']++,
                default => null,
            };

            $principal = (int) $advance->principal_amount_minor;

            if ($advance->disbursed_at !== null
                && $principal > 0
                && (int) $advance->maximum_repayment_days > 0) {
                $aprBps = $this->aprBps($advance);
                $aprTotal += $aprBps;
                $aprLoans++;
                $weightedNumerator += $aprBps * $principal;
                $weightedPrincipal += $principal;
            }
        }

        $summary['average_apr_bps'] = $aprLoans > 0 ? (int) round($aprTotal / $aprLoans) : 0;
        $summary['weighted_apr_bps'] = $weightedPrincipal > 0
            ? (int) round($weightedNumerator / $weightedPrincipal)
            : 0;

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    public function forBusiness(Business $business): array
    {
        $business->loadMissing('advances');

        return $this->summarize($business->advances);
    }

    /**
     * @return array<string, int>
     */
    public function platform(): array
    {
        return $this->summarize(Advance::query()->get());
    }
}
