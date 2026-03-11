<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\Account\AccountSubscription\AccountSubscriptionPlanResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'accountName' => $this->account_name,
            'accountEmail' => $this->account_email,
            'accountPhone' => $this->account_phone,
            'status' => $this->status,
            'activeAccountSubscriptionPlan' => $this->whenLoaded('activeAccountSubscriptionPlan', function () {
                return new AccountSubscriptionPlanResource($this->activeAccountSubscriptionPlan);
            }),
            'billingName' => $this->billing_name,
            'billingEmail' => $this->billing_email,
            'billingPhone' => $this->billing_phone,
            'billingAddress' => $this->billing_address,
            'billingCity' => $this->billing_city,
            'billingProvince' => $this->billing_province,
            'billingZip' => $this->billing_zip,
            'billingCountry' => $this->billing_country,
        ];
    }
}
