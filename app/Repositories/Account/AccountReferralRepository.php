<?php

namespace App\Repositories\Account;

use App\Constant\ReferralConstant;
use App\Models\Account\AccountReferral;

class AccountReferralRepository
{
    /**
     * @param int $invitedAccountId
     *
     * @return AccountReferral|null
     */
    public function findByInvitedAccountId(int $invitedAccountId): ?AccountReferral
    {
        return AccountReferral::where('invited_account_id', $invitedAccountId)->first();
    }

    /**
     * @param int $invitedAccountId
     *
     * @return bool
     */
    public function existsByInvitedAccountId(int $invitedAccountId): bool
    {
        return AccountReferral::where('invited_account_id', $invitedAccountId)->exists();
    }

    /**
     * @param int $referrerAccountId
     * @param int $invitedAccountId
     * @param string $referralCode
     *
     * @return AccountReferral
     */
    public function create(int $referrerAccountId, int $invitedAccountId, string $referralCode): AccountReferral
    {
        return AccountReferral::create([
            'referrer_account_id' => $referrerAccountId,
            'invited_account_id' => $invitedAccountId,
            'referral_code' => $referralCode,
            'status' => ReferralConstant::STATUS_PENDING,
            'reward_applied' => false,
        ]);
    }

    /**
     * @param AccountReferral $referral
     *
     * @return void
     */
    public function markQualified(AccountReferral $referral): void
    {
        $referral->update([
            'status' => ReferralConstant::STATUS_QUALIFIED,
            'qualified_at' => now(),
        ]);
    }

    /**
     * True when the referrer has at least one qualified referral whose reward has not
     * yet been consumed by a discount — i.e. eligible for a 5% discount on the next invoice.
     *
     * @param int $referrerAccountId
     *
     * @return bool
     */
    public function hasUnappliedQualified(int $referrerAccountId): bool
    {
        return AccountReferral::where('referrer_account_id', $referrerAccountId)
            ->qualifiedUnapplied()
            ->exists();
    }

    /**
     * Consume eligibility: mark ALL currently unapplied qualified referrals for this referrer
     * as applied, pointing at the invoice that carried the discount. Extras in the batch are
     * spent together (not banked), enforcing "one 5% per invoice".
     *
     * @param int $referrerAccountId
     * @param int $invoiceId
     *
     * @return int number of referral rows marked applied
     */
    public function markAllQualifiedApplied(int $referrerAccountId, int $invoiceId): int
    {
        return AccountReferral::where('referrer_account_id', $referrerAccountId)
            ->qualifiedUnapplied()
            ->update([
                'reward_applied' => true,
                'reward_applied_at' => now(),
                'reward_invoice_id' => $invoiceId,
            ]);
    }

    /**
     * @param int $referrerAccountId
     * @param string $status
     *
     * @return int
     */
    public function countByStatus(int $referrerAccountId, string $status): int
    {
        return AccountReferral::where('referrer_account_id', $referrerAccountId)
            ->where('status', $status)
            ->count();
    }

    /**
     * Number of distinct invoices that have carried a referral discount for this referrer.
     *
     * @param int $referrerAccountId
     *
     * @return int
     */
    public function countDiscountsEarned(int $referrerAccountId): int
    {
        return AccountReferral::where('referrer_account_id', $referrerAccountId)
            ->where('reward_applied', true)
            ->whereNotNull('reward_invoice_id')
            ->distinct('reward_invoice_id')
            ->count('reward_invoice_id');
    }

    /**
     * All pending referrals (used by the daily safety-net evaluation command).
     *
     * @param int|null $referrerAccountId
     * @param int $limit
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AccountReferral>
     */
    public function getPending(?int $referrerAccountId = null, int $limit = 500)
    {
        return AccountReferral::where('status', ReferralConstant::STATUS_PENDING)
            ->when($referrerAccountId, fn ($q) => $q->where('referrer_account_id', $referrerAccountId))
            ->limit($limit)
            ->get();
    }
}
