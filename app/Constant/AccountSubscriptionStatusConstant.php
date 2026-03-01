<?php

namespace App\Constant;

/**
 * Account (B2B owner) subscription status values.
 */
class AccountSubscriptionStatusConstant
{
    const STATUS_TRIAL = 'trial';
    const STATUS_ACTIVE = 'active';
    const STATUS_TRIAL_EXPIRED = 'trial_expired';
    const STATUS_PAST_DUE = 'past_due';
    const STATUS_CANCELLED = 'cancelled';
}
