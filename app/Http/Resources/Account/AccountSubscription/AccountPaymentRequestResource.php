<?php

namespace App\Http\Resources\Account\AccountSubscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPaymentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'paymentTransaction' => $this->payment_transaction,
            'paymentTransactionId' => $this->payment_transaction_id,
            'amount' => $this->amount,
            'receiptUrl' => $this->receipt_url,
            'receiptFileName' => $this->receipt_file_name,
            'status' => $this->status,
            'createdBy' => $this->requested_by,
            'approvedBy' => $this->approved_by,
            'approvedAt' => $this->approved_at,
            'rejectionReason' => $this->rejection_reason,
            'paymentDetails' => $this->payment_details ? json_decode($this->payment_details, true) : null,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
