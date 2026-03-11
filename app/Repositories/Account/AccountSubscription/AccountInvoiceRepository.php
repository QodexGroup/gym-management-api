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
        return AccountInvoice::create([
            'account_id' => $data['accountId'],
            'account_subscription_plan_id' => $data['accountSubscriptionPlanId'],
            'billing_period' => $data['billingPeriod'],
            'invoice_date' => now(),
            'total_amount' => $data['totalAmount'],
            'discount_amount' => 0,
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

}
