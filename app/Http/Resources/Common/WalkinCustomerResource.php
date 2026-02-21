<?php

namespace App\Http\Resources\Common;

use App\Http\Resources\Core\CustomerResource;
use Illuminate\Http\Resources\Json\JsonResource;

class WalkinCustomerResource extends JsonResource
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
            'customerId' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'checkInTime' => $this->check_in_time,
            'checkOutTime' => $this->check_out_time,
            'status' => $this->status,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'deletedAt' => $this->deleted_at,
        ];
    }
}
