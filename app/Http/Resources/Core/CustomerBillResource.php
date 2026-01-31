<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\Account\MembershipPlanResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerBillResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'customerId' => $this->customer_id,
            'grossAmount' => $this->gross_amount,
            'discountPercentage' => $this->discount_percentage,
            'netAmount' => $this->net_amount,
            'paidAmount' => $this->paid_amount,
            'billDate' => $this->bill_date,
            'billStatus' => $this->bill_status,
            'billType' => $this->bill_type,
            'membershipPlanId' => $this->membership_plan_id,
            'customService' => $this->custom_service,
            'membershipPlan' => $this->whenLoaded('membershipPlan', function () {
                return new MembershipPlanResource($this->membershipPlan);
            }),
            'customerName' => $this->whenLoaded('customer', function () {
                $c = $this->customer;
                return trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: 'N/A';
            }),
            'createdBy' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}

