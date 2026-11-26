<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdvanceLendingResource;
use App\Models\Advance;
use App\Models\Business;
use App\Services\Lending\LendingSummaryService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Admin Actions')]
class LendingController extends Controller
{
    public function __construct(private readonly LendingSummaryService $lending) {}

    public function index(): JsonResponse
    {
        $businesses = Business::query()
            ->whereHas('advances')
            ->with('advances')
            ->get();

        $summary = $this->lending->summarize(
            $businesses->flatMap(fn (Business $business) => $business->advances),
        );

        $rows = $businesses
            ->map(fn (Business $business): array => [
                'business_id' => $business->id,
                'public_id' => $business->public_id,
                'business_name' => $business->business_name,
                'status' => $business->status?->value,
                'summary' => $this->lending->summarize($business->advances),
            ])
            ->sortByDesc(fn (array $row): int => $row['summary']['outstanding_minor'])
            ->values();

        return response()->json([
            'summary' => $summary,
            'businesses' => $rows,
        ]);
    }

    public function show(Business $business): JsonResponse
    {
        $business->load(['advances' => fn ($query) => $query->latest('id')]);

        $advances = $business->advances
            ->map(fn (Advance $advance) => new AdvanceLendingResource(
                $advance,
                $this->lending->aprBps($advance),
            ))
            ->values();

        return response()->json([
            'summary' => $this->lending->summarize($business->advances),
            'advances' => $advances,
        ]);
    }
}
