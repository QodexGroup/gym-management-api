<?php

namespace App\Http\Resources\Platform;

use App\Constant\PlatformConstant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One payment request, in the contract's vocabulary.
 *
 * Two translations happen here and nowhere else:
 *   - `paymentTransaction` becomes a semantic kind. The column holds a model
 *     class name (or the literal 'Reactivation Fee'); leaking an FQCN across
 *     an API would tie the console to GymHub's namespaces.
 *   - `receipt` becomes a short-lived signed URL. `receipt_url` is an R2 object
 *     key, useless to the console and never to be made public.
 *
 * The receipt is attached by PlatformReceiptService before this resource runs,
 * because minting a URL is IO and a resource should not perform IO per row.
 *
 * @mixin \App\Models\Account\AccountPaymentRequest
 */
class PlatformPaymentRequestResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'accountName' => $this->account_name,
            'paymentTransaction' => PlatformConstant::transactionKind($this->payment_transaction),
            'paymentTransactionId' => $this->payment_transaction_id,
            'amount' => round((float) $this->amount, 2),
            'paymentType' => $this->payment_type,
            'status' => $this->status,
            'requestedBy' => $this->requested_by_name,
            'rejectionReason' => $this->rejection_reason,
            'decidedAt' => $this->approved_at?->toIso8601String(),
            'platformActor' => $this->platform_actor,
            'receipt' => $this->platform_receipt,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
