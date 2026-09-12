<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;

/**
 * An account plus its invoices and payment history, for the drill-down.
 *
 * Extends the list resource so the shared fields can never drift between the
 * table and the page it links to.
 *
 * @mixin \App\Models\Account\Account
 */
class PlatformAccountDetailResource extends PlatformAccountResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'invoices' => PlatformInvoiceResource::collection($this->platform_invoices ?? [])->resolve($request),
            'paymentRequests' => PlatformPaymentRequestResource::collection($this->platform_payment_requests ?? [])->resolve($request),
        ];
    }
}
