<?php

namespace App\Http\Controllers\Core;

use App\Constant\UserStatusConstant;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenericRequest;
use App\Services\Core\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getAccountMetrics(GenericRequest $request): JsonResponse
    {
        try {
            $user = $request->getUserData();

            if (!in_array($user->role ?? '', [UserStatusConstant::ADMIN, UserStatusConstant::STAFF], true)) {
                return ApiResponse::error('Forbidden', 403);
            }

            $stats = $this->dashboardService->getStats((int) $user->account_id);

            return ApiResponse::success($stats);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to fetch account metrics', 500);
        }
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getStats(GenericRequest $request): JsonResponse
    {
        try {
            $user = $request->getUserData();
            $accountId = (int) $user->account_id;

            $stats = ($user->role ?? '') === UserStatusConstant::COACH
                ? $this->dashboardService->getCoachDashboardStats($accountId)
                : $this->dashboardService->getStats($accountId);

            return ApiResponse::success($stats);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to fetch dashboard statistics', 500);
        }
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getUpcomingSessions(GenericRequest $request): JsonResponse
    {
        try {
            $user = $request->getUserData();
            $accountId = (int) $user->account_id;
            $limit = min(50, max(1, (int) $request->query('limit', 10)));
            $isCoach = ($user->role ?? '') === UserStatusConstant::COACH;
            $coachId = $isCoach ? (int) $user->id : null;

            $payload = $this->dashboardService->getUpcomingSessionsWithParticipants(
                $accountId,
                $coachId,
                $isCoach,
                $limit
            );

            return ApiResponse::success($payload);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to fetch upcoming sessions', 500);
        }
    }

    /**
     * @param GenericRequest $request
     *
     * @return JsonResponse
     */
    public function getCoachPtClients(GenericRequest $request): JsonResponse
    {
        try {
            $user = $request->getUserData();

            if (($user->role ?? '') !== UserStatusConstant::COACH) {
                return ApiResponse::error('Forbidden', 403);
            }

            $limit = min(50, max(1, (int) $request->query('limit', 10)));
            $payload = $this->dashboardService->getCoachAssignedPtClients(
                (int) $user->account_id,
                (int) $user->id,
                $limit
            );

            return ApiResponse::success($payload);
        } catch (\Throwable $e) {
            return ApiResponse::error('Failed to fetch PT clients', 500);
        }
    }
}
