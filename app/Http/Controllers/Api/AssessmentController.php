<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Business;
use App\Services\Assessments\AssessmentService;

/**
 * @tags Assessments
 */
class AssessmentController extends Controller
{
    public function __construct(private readonly AssessmentService $assessments) {}

    /**
     * Request an ERA assessment
     *
     * Sends business and transaction data to the Python ML/Decision API and
     * persists the returned assessment as a new, immutable snapshot.
     */
    public function store(RequestAssessmentRequest $request, Business $business)
    {
        $this->authorize('manageFinance', $business);

        $assessment = $this->assessments->request(
            $business,
            $request->user(),
            $request->validated(),
        );

        return (new AssessmentResource($assessment))
            ->additional(['message' => 'Assessment requested.'])
            ->response()
            ->setStatusCode(201);
    }
}
