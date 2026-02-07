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
     * Get My Collection stats for the authenticated coach.
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

        try {
            $data = $this->myCollectionService->getStats(
                (int) $user->account_id,
                (int) $user->id
            );

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
