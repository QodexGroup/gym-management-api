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
     * Note: STATUS_EXPIRING is a subset of STATUS_ACTIVE (an expiring membership
     * is still active), which keeps the filter consistent with the stat cards.
     */
    const FILTERABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_EXPIRING,
        self::STATUS_EXPIRED,
    ];
}
