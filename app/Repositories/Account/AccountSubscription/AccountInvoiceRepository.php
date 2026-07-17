<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Models\Account\AccountInvoice;
use Illuminate\Database\Eloquent\Collection;

class AccountInvoiceRepository
{
    /**
     * @param int $accountId
     * @param string $billingPeriod
     *
     * @return bool
     */
    public function existsByAccountAndBillingPeriod(int $accountId, string $billingPeriod): bool
    {
        return AccountInvoice::where('account_id', $accountId)
            ->where('billing_period', $billingPeriod)
            ->exists();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return AccountInvoice
     */
    public function createGeneratedInvoice(array $data): AccountInvoice
    {
        $discountAmount = (float) ($data['discountAmount'] ?? 0);

        return AccountInvoice::create([
            'account_id' => $data['accountId'],
            'account_subscription_plan_id' => $data['accountSubscriptionPlanId'],
            'billing_period' => $data['billingPeriod'],
            'invoice_date' => now(),
            'total_amount' => $data['totalAmount'],
            'discount_amount' => $discountAmount,
            'referral_discount_applied' => $discountAmount > 0,
            'status' => AccountInvoiceStatusConstant::STATUS_PENDING,
            'period_from' => $data['periodFrom'],
            'period_to' => $data['periodTo'],
            'prorate' => $data['prorate'],
            'invoice_details' => json_encode($data['invoiceDetails']),
        ]);
    }

    /**
     * @param array<string> $periods
     *
     * @return Collection<int, AccountInvoice>
     */
    public function getPendingByBillingPeriods(array $periods): Collection
    {
        return AccountInvoice::whereIn('billing_period', $periods)
            ->where('status', AccountInvoiceStatusConstant::STATUS_PENDING)
            ->get();
    }

    /**
     * @param int $invoiceId
     *
     * @return AccountInvoice|null
     */
    public function findByIdWithRelations(int $invoiceId): ?AccountInvoice
    {
        return AccountInvoice::with(['account', 'accountSubscriptionPlan.subscriptionPlan'])->find($invoiceId);
    }

    /**
     * @param int $accountId
     * @param int $exceptInvoiceId
     *
     * @return int
     */
    public function voidUnpaidByAccountIdExceptInvoice(int $accountId, int $exceptInvoiceId): int
    {
        return AccountInvoice::where('account_id', $accountId)
            ->where('id', '!=', $exceptInvoiceId)
            ->where('status', AccountInvoiceStatusConstant::STATUS_PENDING)
            ->update(['status' => AccountInvoiceStatusConstant::STATUS_VOID]);
    }

    /**
     * Mark the given invoice as paid.
     */
    public function markAsPaid(AccountInvoice $invoice): void
    {
        $invoice->update([
            'status' => AccountInvoiceStatusConstant::STATUS_PAID,
        ]);
    }

    /**
     * Get account IDs that have pending invoices from the given account IDs.
     *
     * @param array<int> $accountIds
     *
     * @return array<int>
     */
    public function getAccountIdsWithPendingInvoices(array $accountIds): array
    {
        return AccountInvoice::query()
            ->whereIn('account_id', $accountIds)
            ->where('status', AccountInvoiceStatusConstant::STATUS_PENDING)
            ->pluck('account_id')
            ->unique()
            ->all();
    }

    /**
     * Whether the account already has an outstanding (unpaid/pending) subscription invoice.
     * Used to avoid generating a new invoice while a prior one is still unpaid.
     *
     * @param int $accountId
     *
     * @return bool
     */
    public function hasPendingByAccountId(int $accountId): bool
    {
        return AccountInvoice::query()
            ->where('account_id', $accountId)
            ->where('status', AccountInvoiceStatusConstant::STATUS_PENDING)
            ->exists();
    }

}
