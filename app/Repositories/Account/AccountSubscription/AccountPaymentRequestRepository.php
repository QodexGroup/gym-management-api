<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Helpers\GenericData;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class AccountPaymentRequestRepository
{
    /**
     * @param int $accountId
     *
     * @return AccountPaymentRequest|null
     */
    public function findPendingByAccountId(int $accountId): ?AccountPaymentRequest
    {
        return AccountPaymentRequest::where('account_id', $accountId)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->first();
    }


    /**
     * Create a payment request linked to an invoice using GenericData + invoice model.
     */
    public function createInvoicePaymentRequest(GenericData $genericData, AccountInvoice $invoice): AccountPaymentRequest
    {
        $data = $genericData->getData();

        return AccountPaymentRequest::create([
            'account_id' => $genericData->userData->account_id,
            'payment_transaction' => AccountInvoice::class,
            'payment_transaction_id' => $invoice->id,
            'receipt_url' => trim((string) $data->receiptUrl),
            'receipt_file_name' => $data->receiptFileName ?? null,
            'status' => AccountPaymentRequestStatusConstant::STATUS_PENDING,
            'requested_by' => $genericData->userData->id,
            'payment_details' => json_encode([
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
            ]),
        ]);
    }

    /**
     * @param int $id
     *
     * @return AccountPaymentRequest|null
     */
    public function findById(int $id): ?AccountPaymentRequest
    {
        return AccountPaymentRequest::find($id);
    }

    /**
     * @param int $id
     *
     * @return AccountPaymentRequest|null
     */
    public function findPendingById(int $id): ?AccountPaymentRequest
    {
        return AccountPaymentRequest::where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->find($id);
    }

    /**
     * Paginate payment requests for the current account, most recent first.
     */
    public function paginateByAccount(GenericData $genericData): LengthAwarePaginator
    {
        $query = AccountPaymentRequest::where('account_id', $genericData->userData->account_id)
            ->orderByDesc('created_at');

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * @param int $accountId
     * @param string $paymentTransaction
     * @param int|null $paymentTransactionId
     *
     * @return bool
     */
    public function hasPendingForAccount(int $accountId, string $paymentTransaction, ?int $paymentTransactionId = null): bool
    {
        $query = AccountPaymentRequest::where('account_id', $accountId)
            ->where('payment_transaction', $paymentTransaction)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING);

        if ($paymentTransactionId !== null) {
            $query->where('payment_transaction_id', $paymentTransactionId);
        }

        return $query->exists();
    }

    /**
     * @return Collection<int, AccountPaymentRequest>
     */
    public function getApprovedInvoiceRequests(?int $accountId = null, int $limit = 200): Collection
    {
        return AccountPaymentRequest::with('account')
            ->where('payment_transaction', AccountInvoice::class)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_APPROVED)
            ->when($accountId !== null, function (Builder $builder) use ($accountId) {
                $builder->where('account_id', $accountId);
            })
            ->orderBy('approved_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a payment request as approved.
     *
     * @param AccountPaymentRequest $request
     * @param int|null $adminUserId
     *
     * @return void
     */
    public function markAsApproved(AccountPaymentRequest $request, ?int $adminUserId = null): void
    {
        $request->update([
            'status' => AccountPaymentRequestStatusConstant::STATUS_APPROVED,
            'approved_by' => $adminUserId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark a payment request as rejected.
     *
     * @param AccountPaymentRequest $request
     * @param int|null $adminUserId
     * @param string|null $reason
     *
     * @return void
     */
    public function markAsRejected(AccountPaymentRequest $request, ?int $adminUserId = null, ?string $reason = null): void
    {
        $request->update([
            'status' => AccountPaymentRequestStatusConstant::STATUS_REJECTED,
            'approved_by' => $adminUserId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Update payment details payload.
     *
     * @param AccountPaymentRequest $request
     * @param array $paymentDetails
     *
     * @return void
     */
    public function updatePaymentDetails(AccountPaymentRequest $request, array $paymentDetails): void
    {
        $request->update([
            'payment_details' => json_encode($paymentDetails),
        ]);
    }
}
