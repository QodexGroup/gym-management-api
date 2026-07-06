<?php

namespace App\Data;

/**
 * Typed return object for the account owner's referral summary.
 */
class ReferralSummary
{
    public string $code;
    public string $shareUrl;
    public int $pendingCount;
    public int $qualifiedCount;
    public int $totalDiscountsEarned;
    public bool $isEligibleNextInvoice;
    public float $discountPercent;
}
