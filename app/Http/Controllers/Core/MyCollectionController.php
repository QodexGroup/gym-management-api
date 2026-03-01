<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\MyCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyCollectionController extends Controller
{
    public function __construct(
        private MyCollectionService $myCollectionService
    ) {
    }

    /**
     * Get My Collection stats. Admin sees account-wide aggregate; coach sees own data only.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $role = $user->role ?? null;
        if (!in_array($role, ['admin', 'coach'], true)) {
            return response()->json(['success' => false, 'message' => 'My Collection is available only for admin or coach.'], 403);
        }

        $accountId = isset($user->account_id) ? (int) $user->account_id : null;
        if ($accountId === null || $accountId <= 0) {
            return response()->json(['success' => false, 'message' => 'Account is required for My Collection.'], 400);
        }

        $coachId = $role === 'admin' ? null : (int) $user->id;

        try {
            $data = $this->myCollectionService->getStats($accountId, $coachId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch My Collection stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
