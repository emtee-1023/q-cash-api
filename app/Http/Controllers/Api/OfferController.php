<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptOfferRequest;
use App\Http\Requests\StoreOfferRequest;
use App\Http\Resources\AdvanceResource;
use App\Http\Resources\OfferResource;
use App\Models\AdvanceOffer;
use App\Models\EraAssessment;
use App\Models\FinancingPartner;
use App\Services\Advances\AdvanceService;
use App\Services\Advances\OfferService;

/**
 * @tags Offers
 */
class OfferController extends Controller
{
    public function __construct(
        private readonly OfferService $offers,
        private readonly AdvanceService $advances,
    ) {}

    /**
     * Create an advance offer
     *
     * Builds an offer from an eligible assessment, snapshotting the complete
     * terms presented to the business owner.
     */
    public function store(StoreOfferRequest $request)
    {
        $assessment = EraAssessment::findOrFail($request->integer('assessment_id'));

        $this->authorize('manageFinance', $assessment->business);

        $offer = $this->offers->createOffer($assessment, $request->validated());

        return (new OfferResource($offer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Accept an offer
     *
     * Accepts a pending offer and creates the corresponding advance with its
     * original repayment terms.
     */
    public function accept(AcceptOfferRequest $request, AdvanceOffer $offer)
    {
        $this->authorize('manageFinance', $offer->business);

        $partner = $request->filled('financing_partner_id')
            ? FinancingPartner::findOrFail($request->integer('financing_partner_id'))
            : null;

        $advance = $this->advances->acceptOffer($offer, $partner);

        return (new AdvanceResource($advance))
            ->response()
            ->setStatusCode(201);
    }
}
