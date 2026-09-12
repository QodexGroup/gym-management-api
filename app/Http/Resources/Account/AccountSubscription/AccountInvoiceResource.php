<?php

namespace App\Http\Resources\Account\AccountSubscription;

use App\Support\Platform\InvoiceStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the signed-in account's own subscription invoices.
 *
 * `status` is DERIVED, not a column: a pending invoice past the lock day
 * reports as `overdue`. The same resolver the operations console uses is used
 * here on purpose — an owner and an operator disagreeing about whether an
 * invoice is overdue would be worse than either answer.
 *
 * @mixin \App\Models\Account\AccountInvoice
 */
class AccountInvoiceResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = $this->invoice_details ? json_decode($this->invoice_details, true) : null;
        $latest = $this->latestPaymentRequest;

        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoice_number,
            'billingPeriod' => $this->billing_period,
            'status' => InvoiceStatusResolver::resolve($this->resource),
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'dueDate' => InvoiceStatusResolver::dueDate($this->resource)?->toDateString(),
            'periodFrom' => $this->period_from?->toDateString(),
            'periodTo' => $this->period_to?->toDateString(),
            'totalAmount' => round((float) $this->total_amount, 2),
            'discountAmount' => round((float) $this->discount_amount, 2),
            'invoiceType' => is_array($details) ? ($details['invoiceType'] ?? null) : null,
            // Domain state, not a UI flag: the owner cannot submit a second
            // receipt while one is still awaiting review, and the create
            // endpoint enforces that independently.
            'hasPendingPaymentRequest' => (int) ($this->pending_payment_requests_count ?? 0) > 0,
            'latestPaymentRequest' => $latest ? [
                'id' => $latest->id,
                'status' => $latest->status,
                'receiptUrl' => $latest->receipt_url,
                'receiptFileName' => $latest->receipt_file_name,
                'createdAt' => $latest->created_at?->toIso8601String(),
            ] : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
