<?php

namespace App\Constant;

/**
 * Keys for metered account resources tracked in the `account_usages` table.
 * Add a new key here (and a default in config/quotas.php) to meter a new feature.
 */
class ResourceKeyConstant
{
    const STORAGE = 'storage';
    // const SMS_CREDITS = 'sms_credits';  // example future resource
}
