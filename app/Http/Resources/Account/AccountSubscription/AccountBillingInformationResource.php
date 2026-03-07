<?php

namespace App\Http\Resources\Account\AccountSubscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountBillingInformationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accountId' => $this->id,
            'legalName' => $this->legal_name,
            'billingEmail' => $this->billing_email,
            'addressLine1' => $this->address_line_1,
            'addressLine2' => $this->address_line_2,
            'city' => $this->city,
            'stateProvince' => $this->state_province,
            'postalCode' => $this->postal_code,
            'country' => $this->country,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
