<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountSubscriptionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'subscriptionPlanId' => $this->subscription_plan_id,
            'receiptUrl' => $this->receipt_url,
            'receiptFileName' => $this->receipt_file_name,
            'status' => $this->status,
            'requestedBy' => $this->requested_by,
            'approvedBy' => $this->approved_by,
            'approvedAt' => $this->approved_at ? $this->approved_at->toIso8601String() : null,
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'subscriptionStatus' => $this->account->subscription_status,
                ];
            }),
            'subscriptionPlan' => $this->whenLoaded('subscriptionPlan', function () {
                return [
                    'id' => $this->subscriptionPlan->id,
                    'name' => $this->subscriptionPlan->name,
                    'slug' => $this->subscriptionPlan->slug,
                ];
            }),
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
