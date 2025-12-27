<?php

namespace App\Services\Auth;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;

class FirebaseService
{
    protected static ?Auth $auth = null;

    public static function auth(): Auth
    {
        if (!self::$auth) {
            $factory = (new Factory)->withServiceAccount(storage_path('firebase_credentials.json'));
            self::$auth = $factory->createAuth();
        }

        return self::$auth;
    }
}
