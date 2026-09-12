<?php

namespace App\Http\Controllers\Platform;

use App\Helpers\PlatformResponse;
use App\Http\Requests\Platform\PlatformSummaryRequest;
use App\Services\Platform\PlatformSummaryService;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard counters for one product.
 */
class PlatformSummaryController
{
    public function __construct(
        private PlatformSummaryService $summaryService,
    ) {
    }

    /**
     * @param PlatformSummaryRequest $request
     *
     * @return JsonResponse
     */
    public function getSummary(PlatformSummaryRequest $request): JsonResponse
    {
        return PlatformResponse::item(
            $this->summaryService->getSummary($request->input('from'), $request->input('to')),
        );
    }
}
