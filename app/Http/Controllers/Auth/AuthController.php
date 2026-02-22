<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignUpRequest;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Account\UserResource;
use App\Services\Account\AccountLimitService;
use App\Services\Account\AccountSignUpService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AccountSignUpService $signUpService,
        private AccountLimitService $limitService
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

            $message = $result['isNew']
                ? 'Account created successfully. Your 7-day free trial has started. Enjoy all features!'
                : 'Welcome back.';

            return ApiResponse::success([
                'user' => new UserResource($result['user']),
                'account' => new AccountResource($result['account']),
                'usage' => $this->limitService->getUsageSummary($result['account']->id),
            ], $message, 201);
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

            // Load permissions and account with plan
            if (!$user->relationLoaded('permissions')) {
                $user->load('permissions');
            }
            if (!$user->relationLoaded('account')) {
                $user->load('account.subscriptionPlan');
            }

            $data = (new UserResource($user))->toArray($request);
            $data['account'] = $user->account ? (new AccountResource($user->account))->toArray($request) : null;
            $data['usage'] = $this->limitService->getUsageSummary($user->account_id);
            $adminEmails = config('app.platform_admin_emails', []);
            $data['isPlatformAdmin'] = !empty($adminEmails) && in_array($user->email, $adminEmails, true);
            $data['emailVerified'] = $request->attributes->get('email_verified', false);

            return ApiResponse::success($data, 'User retrieved successfully');

        } catch (\Exception $e) {
            return ApiResponse::error('Error fetching user data: ' . $e->getMessage(), 500);
        }
    }
}

