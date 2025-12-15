<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPaymentResource extends JsonResource
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
            'customerBillId' => $this->customer_bill_id,
            'amount' => $this->amount,
            'paymentMethod' => $this->payment_method,
            'paymentDate' => $this->payment_date,
            'referenceNumber' => $this->reference_number,
            'remarks' => $this->remarks,
            'bill' => $this->whenLoaded('bill', function () {
                return new CustomerBillResource($this->bill);
            }),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}


