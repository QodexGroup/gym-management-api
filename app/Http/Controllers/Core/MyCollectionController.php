<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenericRequest;
use App\Services\Core\MyCollectionService;
use Illuminate\Http\JsonResponse;

class MyCollectionController extends Controller
{
    public function __construct(
        private MyCollectionService $myCollectionService
    ) {
    }

    /**
     * Get My Collection stats for coach.
     *
     * @param GenericRequest $request
     * @return JsonResponse
     */
    public function getStats(GenericRequest $request): JsonResponse
    {
        try {
            $genericData = $request->getGenericData();
            $accountId = $genericData->userData->account_id;
            $coachId = $genericData->userData->id;

            $data = $this->myCollectionService->getStats($accountId, $coachId);

            return ApiResponse::success($data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch My Collection stats: ' . $e->getMessage(), 500);
        }
    }
}
