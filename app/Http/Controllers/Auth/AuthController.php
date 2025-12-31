<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Account\UserResource;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Get the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            if (!$user) {
                return ApiResponse::error('User not found', 404);
            }

            // Load permissions if not already loaded (may be empty for admin users)
            if (!$user->relationLoaded('permissions')) {
                $user->load('permissions');
            }

            return ApiResponse::success(new UserResource($user), 'User retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Error fetching user data: ' . $e->getMessage(), 500);
        }
    }
}

