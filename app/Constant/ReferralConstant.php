<?php

namespace App\Constant;

/**
 * Referral / invitation discount program values.
 */
class ReferralConstant
{
    // account_referrals.status
    public const STATUS_PENDING      = 'pending';
    public const STATUS_QUALIFIED    = 'qualified';
    public const STATUS_DISQUALIFIED = 'disqualified';

    // Business rule: flat 5% of the plan charge, capped at one referral discount per invoice.
    public const DISCOUNT_PERCENT = 5.0;

    // Referral code format, e.g. "GYMHUB-A1B2".
    public const CODE_PREFIX        = 'GYMHUB-';
    public const CODE_RANDOM_LENGTH = 4;
    // Characters used for the random segment (no ambiguous 0/O/1/I).
    public const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
}
