<?php

namespace App\Http\Middleware;

use App\Services\Auth\FirebaseService;
use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies Firebase ID token and sets firebase_uid in request.
 * Does NOT require user to exist in DB (used for sign-up).
 */
class VerifyFirebaseTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthorized. Token required.'], 401);
        }

        try {
            $verifiedToken = FirebaseService::auth()->verifyIdToken($token);
            $firebaseUid = $verifiedToken->claims()->get('sub');
            $request->attributes->set('firebase_uid', $firebaseUid);
            $request->attributes->set('firebase_email', $verifiedToken->claims()->get('email'));
        } catch (FailedToVerifyToken $e) {
            return response()->json(['message' => 'Invalid or expired token'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentication failed'], 401);
        }

        return $next($request);
    }
}
