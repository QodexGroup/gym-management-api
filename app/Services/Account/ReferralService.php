<?php

namespace App\Services\Account;

use App\Constant\ReferralConstant;
use App\Data\ReferralSummary;
use App\Models\Account\Account;
use App\Repositories\Account\AccountReferralRepository;
use App\Repositories\Account\AccountRepository;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionPlanRepository;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    public function __construct(
        private AccountRepository $accountRepository,
        private AccountReferralRepository $referralRepository,
        private AccountSubscriptionPlanRepository $accountSubscriptionPlanRepository,
    ) {
    }

    /**
     * Return the account's referral code, generating and persisting one on first use.
     *
     * @param int $accountId
     *
     * @return string
     */
    public function getOrCreateReferralCode(int $accountId): string
    {
        $account = $this->accountRepository->findById($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Account not found.');
        }

        if (!empty($account->referral_code)) {
            return $account->referral_code;
        }

        $code = $this->generateUniqueCode();
        $this->accountRepository->saveReferralCode($account, $code);

        return $code;
    }

    /**
     * Attach a referral record when a new account signs up using a referral code.
     * No reward is granted here — a trial signup must never qualify the referrer.
     *
     * @param int $newAccountId
     * @param string|null $code
     *
     * @return void
     */
    public function attachReferralOnSignup(int $newAccountId, ?string $code): void
    {
        $code = $code ? trim($code) : null;
        if (!$code) {
            return;
        }

        $referrer = $this->accountRepository->findByReferralCode($code);
        if (!$referrer) {
            return;
        }

        // Guard: self-referral.
        if ($referrer->id === $newAccountId) {
            return;
        }

        // Guard: an account can only ever be referred once.
        if ($this->referralRepository->existsByInvitedAccountId($newAccountId)) {
            return;
        }

        $this->referralRepository->create($referrer->id, $newAccountId, $code);

        Log::info('Referral attached on signup', [
            'referrer_account_id' => $referrer->id,
            'invited_account_id' => $newAccountId,
            'referral_code' => $code,
        ]);
    }

    /**
     * Evaluate an invited account: if it has a started, paid (non-trial) subscription and its
     * referral is still pending, mark it qualified — making the referrer eligible for one 5%
     * discount on their next invoice. Idempotent and safe to call repeatedly.
     *
     * @param int $invitedAccountId
     *
     * @return void
     */
    public function evaluateInvitedAccount(int $invitedAccountId): void
    {
        $referral = $this->referralRepository->findByInvitedAccountId($invitedAccountId);
        if (!$referral || $referral->status !== ReferralConstant::STATUS_PENDING) {
            return;
        }

        if (!$this->invitedHasPaidSubscription($invitedAccountId)) {
            return;
        }

        $this->referralRepository->markQualified($referral);

        Log::info('Referral qualified (invitee completed a paid subscription payment)', [
            'referrer_account_id' => $referral->referrer_account_id,
            'invited_account_id' => $invitedAccountId,
        ]);
    }

    /**
     * @param int $accountId
     *
     * @return bool
     */
    public function isEligibleForDiscount(int $accountId): bool
    {
        return $this->referralRepository->hasUnappliedQualified($accountId);
    }

    /**
     * Compute the flat 5% discount from the invoice's plan charge (plan only, no other fees).
     *
     * @param float $planCharge
     *
     * @return float
     */
    public function computeDiscount(float $planCharge): float
    {
        return round($planCharge * ReferralConstant::DISCOUNT_PERCENT / 100, 2);
    }

    /**
     * Consume the eligibility after a discount has been applied to an invoice.
     * Marks all currently-unapplied qualified referrals as applied (extras spent, not banked).
     *
     * @param int $accountId
     * @param int $invoiceId
     *
     * @return void
     */
    public function consumeDiscountForInvoice(int $accountId, int $invoiceId): void
    {
        $this->referralRepository->markAllQualifiedApplied($accountId, $invoiceId);
    }

    /**
     * Build the referral summary for the account owner (auto-creates the code).
     *
     * @param int $accountId
     *
     * @return ReferralSummary
     */
    public function getReferralSummary(int $accountId): ReferralSummary
    {
        $code = $this->getOrCreateReferralCode($accountId);

        $summary = new ReferralSummary();
        $summary->code = $code;
        $summary->shareUrl = $this->buildShareUrl($code);
        $summary->pendingCount = $this->referralRepository->countByStatus($accountId, ReferralConstant::STATUS_PENDING);
        $summary->qualifiedCount = $this->referralRepository->countByStatus($accountId, ReferralConstant::STATUS_QUALIFIED);
        $summary->totalDiscountsEarned = $this->referralRepository->countDiscountsEarned($accountId);
        $summary->isEligibleNextInvoice = $this->referralRepository->hasUnappliedQualified($accountId);
        $summary->discountPercent = ReferralConstant::DISCOUNT_PERCENT;

        return $summary;
    }

    /**
     * @param int $invitedAccountId
     *
     * @return bool
     */
    private function invitedHasPaidSubscription(int $invitedAccountId): bool
    {
        $asp = $this->accountSubscriptionPlanRepository->findLatestByAccountIdWithPlan($invitedAccountId);

        return $asp
            && $asp->subscriptionPlan
            && !$asp->subscriptionPlan->is_trial
            && $asp->subscription_starts_at !== null;
    }

    /**
     * @return string
     */
    private function generateUniqueCode(): string
    {
        do {
            $random = '';
            $alphabet = ReferralConstant::CODE_ALPHABET;
            for ($i = 0; $i < ReferralConstant::CODE_RANDOM_LENGTH; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = ReferralConstant::CODE_PREFIX . $random;
        } while ($this->accountRepository->referralCodeExists($code));

        return $code;
    }

    /**
     * @param string $code
     *
     * @return string
     */
    private function buildShareUrl(string $code): string
    {
        $base = rtrim((string) env('APP_URL', 'https://staging.gymhubph.com'), '/');

        return $base . '/signup?ref=' . urlencode($code);
    }
}
