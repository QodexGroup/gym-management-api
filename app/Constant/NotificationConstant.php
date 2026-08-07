<?php

namespace App\Constant;

class NotificationConstant
{
    // Notification Types
    const TYPE_MEMBERSHIP_EXPIRING = 'membership_expiring';
    const TYPE_PAYMENT_RECEIVED = 'payment_received';
    const TYPE_CUSTOMER_REGISTERED = 'customer_registered';
    const TYPE_IMPORT_COMPLETED = 'import_completed';

    // Settings
    const MEMBERSHIP_EXPIRATION_DAYS_THRESHOLD = 7;
}
