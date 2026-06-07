<?php

namespace App\Http\Middleware;

use App\Constant\UserStatusConstant;
use App\Models\User;
use App\Services\Auth\FirebaseService;
use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Symfony\Component\HttpFoundation\Response;

class FirebaseAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $verifiedToken = FirebaseService::auth()->verifyIdToken($token);
            $firebaseUid = $verifiedToken->claims()->get('sub');
            $emailVerified = $verifiedToken->claims()->get('email_verified') ?? false;

            $users = User::with('permissions')
                ->where('firebase_uid', $firebaseUid)
                ->whereNull('deleted_at')
                ->get();

            if ($users->isEmpty()) {
                return response()->json(['message' => 'User not found'], 401);
            }

            if ($users->count() > 1) {
                return response()->json([
                    'message' => 'Multiple accounts are linked to this login. Please contact support.',
                ], 409);
            }

            $user = $users->first();

            if ($user->status === UserStatusConstant::DEACTIVATED) {
                return response()->json([
                    'message' => 'Your account has been deactivated. Please contact the administrator.'
                ], 403);
            }

            $request->attributes->set('user', $user);
            $request->attributes->set('email_verified', $emailVerified);

        } catch (FailedToVerifyToken $e) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentication failed'], 401);
        }

        return $next($request);
    }
}
