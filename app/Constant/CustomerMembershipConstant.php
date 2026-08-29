<?php

namespace App\Constant;

class CustomerMembershipConstant
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DEACTIVATED = 'deactivated';
    const STATUS_EXPIRED = 'expired';
    const STATUS_EXPIRING = 'expiring';

    /**
     * How many days ahead a membership counts as "expiring soon".
     * Mirrors the notification threshold so the client list, the dashboard and
     * the expiry reminders all agree on what "expiring soon" means.
     */
    const EXPIRING_SOON_DAYS = NotificationConstant::MEMBERSHIP_EXPIRATION_DAYS_THRESHOLD;

    /**
     * Statuses accepted by the `membershipStatus` filter on the customer list.
     * These are mutually exclusive: a membership inside the expiring window is
     * reported as STATUS_EXPIRING only, never also as STATUS_ACTIVE, so the
     * stat cards partition the clients the same way the row badges do.
     * See CustomerMembership::scopeWithStatus().
     */
    const FILTERABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRING,
        self::STATUS_EXPIRED,
    ];
}
