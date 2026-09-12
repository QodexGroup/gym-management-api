<?php

namespace App\Http\Resources\Platform;

use App\Support\Platform\InvoiceStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One invoice. `status` is the DERIVED status — a pending invoice past the
 * lock day reports as `overdue`, which no column ever stores.
 *
 * @mixin \App\Models\Account\AccountInvoice
 */
class PlatformInvoiceResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $details = $this->invoice_details ? json_decode($this->invoice_details, true) : null;

        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'accountName' => $this->account_name,
            'invoiceNumber' => $this->invoice_number,
            'status' => InvoiceStatusResolver::resolve($this->resource),
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'dueDate' => InvoiceStatusResolver::dueDate($this->resource)?->toDateString(),
            'periodFrom' => $this->period_from?->toDateString(),
            'periodTo' => $this->period_to?->toDateString(),
            'totalAmount' => round((float) $this->total_amount, 2),
            'discountAmount' => round((float) $this->discount_amount, 2),
            'invoiceType' => is_array($details) ? ($details['invoiceType'] ?? null) : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
