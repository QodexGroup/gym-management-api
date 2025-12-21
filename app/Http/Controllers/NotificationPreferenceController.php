<?php

namespace App\Http\Controllers;

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
    public function index(): JsonResponse
    {
        $preferences = $this->repository->getByAccountId(1);

        if (!$preferences) {
            // Return defaults if no preferences exist
            return response()->json([
                'data' => $this->repository->getDefaults()
            ]);
        }

        return response()->json([
            'data' => [
                'membership_expiry_enabled' => $preferences->membership_expiry_enabled,
                'payment_alerts_enabled' => $preferences->payment_alerts_enabled,
                'new_registrations_enabled' => $preferences->new_registrations_enabled,
            ]
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
        $data = $request->validate([
            'membership_expiry_enabled' => 'sometimes|boolean',
            'payment_alerts_enabled' => 'sometimes|boolean',
            'new_registrations_enabled' => 'sometimes|boolean',
        ]);

        $preferences = $this->repository->updateOrCreate(1, $data);

        return response()->json([
            'message' => 'Preferences updated successfully',
            'data' => [
                'membership_expiry_enabled' => $preferences->membership_expiry_enabled,
                'payment_alerts_enabled' => $preferences->payment_alerts_enabled,
                'new_registrations_enabled' => $preferences->new_registrations_enabled,
            ]
        ]);
    }
}
