<?php

namespace App\Http\Controllers\Core;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\MyReportRequest;
use App\Services\Core\MyRevenueService;
use Illuminate\Http\JsonResponse;

class MyRevenueController extends Controller
{
    public function __construct(
        private MyRevenueService $myRevenueService
    ) {
    }

    /**
     * Get My Revenue report data for a coach (date-range filtered).
     *
     * @param MyReportRequest $request
     * @return JsonResponse
     */
    public function getStats(MyReportRequest $request): JsonResponse
    {
        try {
            $data = $this->myRevenueService->getStats($request->getGenericDataWithValidated());

            return ApiResponse::success($data);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch My Revenue stats: ' . $e->getMessage(), 500);
        }
    }
}
