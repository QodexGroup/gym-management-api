<?php

namespace App\Http\Resources\Account\AccountSubscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountSubscriptionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoice = $this->relationLoaded('invoice') ? $this->invoice : null;
        $invoiceSubscriptionPlan = $invoice ? $invoice->accountSubscriptionPlan : null;
        $invoicePlatformPlan = $invoiceSubscriptionPlan ? $invoiceSubscriptionPlan->platformPlan : null;
        $plan = $invoicePlatformPlan ?: $this->getSubscriptionPlanAttribute();

        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'invoiceId' => $this->account_invoice_id,
            'invoiceNumber' => $invoice ? $invoice->invoice_number : null,
            'billingPeriod' => $invoice ? $invoice->billing_period : null,
            'invoiceDetails' => $invoice ? $invoice->invoice_details : null,
            'receiptUrl' => $this->receipt_url,
            'receiptFileName' => $this->receipt_file_name,
            'status' => $this->status,
            'requestedBy' => $this->requested_by,
            'approvedBy' => $this->approved_by,
            'approvedAt' => $this->approved_at,
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => $this->created_at,
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'subscriptionStatus' => $this->account->subscription_status,
                ];
            }),
            'subscriptionPlan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ] : null,
            'requestedByUser' => $this->whenLoaded('requestedByUser', function () {
                return [
                    'id' => $this->requestedByUser->id,
                    'fullname' => $this->requestedByUser->full_name,
                    'email' => $this->requestedByUser->email,
                ];
            }),
        ];
    }
}
