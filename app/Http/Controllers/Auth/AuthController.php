<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignUpRequest;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Account\UserResource;
use App\Services\Account\AccountSignUpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct(
        private AccountSignUpService $signUpService
    ) {
    }

    /**
     * Sign up: create account + first user (requires valid Firebase token).
     */
    public function signUp(SignUpRequest $request)
    {
        try {
            $firebaseUid = $request->attributes->get('firebase_uid');
            if (!$firebaseUid) {
                return ApiResponse::error('Authentication required', 401);
            }

            $result = $this->signUpService->signUp($firebaseUid, $request->validated());

            return ApiResponse::success([
                'user' => new UserResource($result['user']),
                'account' => new AccountResource($result['account']),
            ], 'Account created successfully. Your 7-day free trial has started. Enjoy all features!', 201);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

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

            // Load permissions and account with active subscription plan
            $user->loadMissing(['permissions', 'account.activeAccountSubscriptionPlan.subscriptionPlan']);
            return ApiResponse::success(
                new UserResource($user),
                'User retrieved successfully'
            );

        } catch (\Exception $e) {
            return ApiResponse::error('Error fetching user data: ' . $e->getMessage(), 500);
        }
    }
}

