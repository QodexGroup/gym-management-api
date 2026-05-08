<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenericRequest;
use App\Services\Core\DashboardService;
use Illuminate\Http\JsonResponse;
use Throwable;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonError(string $message, int $status, ?Throwable $e = null, array $payload = []): JsonResponse
    {
        $body = array_merge(['success' => false, 'message' => $message], $payload);
        if ($e !== null && config('app.debug')) {
            $body['error'] = $e->getMessage();
        }

        return response()->json($body, $status);
    }

    /**
     * Full account dashboard metrics (admin/staff only). Used by reports and admin surfaces.
     */
    public function getAccountMetrics(GenericRequest $request): JsonResponse
    {
        try {
            $user = $request->getUserData();
            $role = (string) ($user->role ?? '');

            if (!in_array($role, ['admin', 'staff'], true)) {
                return $this->jsonError('Forbidden', 403);
            }

            $stats = $this->dashboardService->getStats((int) $user->account_id);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError('Failed to fetch account metrics', 500, $e);
        }
    }

    /**
     * Get dashboard statistics
     *
     * @return JsonResponse
     */
    public function getStats(GenericRequest $request): JsonResponse
    {
        try {
            $getUserData = $request->getUserData();
            $accountId = (int) $getUserData->account_id;

            if (($getUserData->role ?? '') === 'coach') {
                $stats = $this->dashboardService->getCoachDashboardStats($accountId);
            } else {
                $stats = $this->dashboardService->getStats($accountId);
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError('Failed to fetch dashboard statistics', 500, $e);
        }
    }

    /**
     * Today's and upcoming class / PT sessions with participants (dashboards).
     */
    public function getUpcomingSessions(GenericRequest $request): JsonResponse
    {
        try {
            $user = $request->getUserData();
            $accountId = (int) $user->account_id;
            $limit = min(50, max(1, (int) $request->query('limit', 10)));
            $isCoach = isset($user->role) && $user->role === 'coach';
            $coachId = $isCoach ? (int) $user->id : null;

            $payload = $this->dashboardService->getUpcomingSessionsWithParticipants(
                $accountId,
                $coachId,
                $isCoach,
                $limit
            );

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError('Failed to fetch upcoming sessions', 500, $e);
        }
    }

    /**
     * Coach-only: assigned PT clients for dashboard list.
     */
    public function getCoachPtClients(GenericRequest $request): JsonResponse
    {
        try {
            $user = $request->getUserData();
            if (!isset($user->role) || $user->role !== 'coach') {
                return $this->jsonError('Forbidden', 403);
            }

            $limit = min(50, max(1, (int) $request->query('limit', 10)));
            $payload = $this->dashboardService->getCoachAssignedPtClients(
                (int) $user->account_id,
                (int) $user->id,
                $limit
            );

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError('Failed to fetch PT clients', 500, $e);
        }
    }
}
