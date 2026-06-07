<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Repositories\Core\NotificationPreferenceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationPreferenceRepository $repository
    ) {
    }

    /**
     * Get notification preferences.
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $accountId = $this->getAccountId($request);
        $preferences = $this->repository->getByAccountId($accountId);

        if (!$preferences) {
            return ApiResponse::success($this->repository->getDefaults());
        }

        return ApiResponse::success([
            'membership_expiry_enabled' => $preferences->membership_expiry_enabled,
            'payment_alerts_enabled' => $preferences->payment_alerts_enabled,
            'new_registrations_enabled' => $preferences->new_registrations_enabled,
        ]);
    }

    /**
     * Update notification preferences.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $accountId = $this->getAccountId($request);

        $data = $request->validate([
            'membership_expiry_enabled' => 'sometimes|boolean',
            'payment_alerts_enabled' => 'sometimes|boolean',
            'new_registrations_enabled' => 'sometimes|boolean',
        ]);

        $preferences = $this->repository->updateOrCreate($accountId, $data);

        return ApiResponse::success([
            'membership_expiry_enabled' => $preferences->membership_expiry_enabled,
            'payment_alerts_enabled' => $preferences->payment_alerts_enabled,
            'new_registrations_enabled' => $preferences->new_registrations_enabled,
        ], 'Preferences updated successfully');
    }

    /**
     * Get the authenticated user's account ID from the request.
     *
     * @param Request $request
     * @return int
     */
    private function getAccountId(Request $request): int
    {
        $user = $request->attributes->get('user');

        if (!$user || !$user->account_id) {
            abort(401, 'Unauthorized');
        }

        return (int) $user->account_id;
    }
}
