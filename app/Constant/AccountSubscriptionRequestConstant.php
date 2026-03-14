<?php

namespace App\Constant;

/**
 * Account subscription request (owner upgrade) status and messages.
 */
class AccountSubscriptionRequestConstant
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const MESSAGE_ALREADY_ACTIVE = 'Account already has an active subscription. Upgrade or change plan from settings.';
    const MESSAGE_PENDING_EXISTS = 'You already have a pending subscription request. Please wait for approval.';
}
