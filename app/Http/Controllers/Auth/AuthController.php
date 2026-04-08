<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SignUpRequest;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Account\UserResource;
use App\Mail\EmailVerificationMail;
use App\Services\Account\AccountSignUpService;
use App\Services\Auth\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Generate a Firebase email verification link and send a branded email.
     */
    public function sendVerificationEmail(Request $request)
    {
        try {
            $email = $request->attributes->get('firebase_email');

            if (!$email) {
                return ApiResponse::error('Email not found in token.', 400);
            }

            $firebaseLink = FirebaseService::auth()->getEmailVerificationLink($email);
            $parsed = parse_url($firebaseLink);
            parse_str($parsed['query'] ?? '', $queryParams);
            $oobCode = $queryParams['oobCode'] ?? null;

            if (!$oobCode) {
                Log::error('Failed to extract oobCode from Firebase verification link', ['link' => $firebaseLink]);
                return ApiResponse::error('Failed to generate verification link.', 500);
            }

            $frontendUrl = rtrim(env('FRONTEND_URL', 'https://gymhubtech-67e6f.web.app'), '/');
            $verificationUrl = $frontendUrl . '/auth/action?mode=verifyEmail&oobCode=' . urlencode($oobCode);

            Mail::to($email)->send(new EmailVerificationMail($verificationUrl));

            return ApiResponse::success(null, 'Verification email sent.');
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', ['error' => $e->getMessage()]);
            return ApiResponse::error('Failed to send verification email. Please try again.', 500);
        }
    }
}

