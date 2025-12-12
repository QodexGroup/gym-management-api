<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\Account\MembershipPlanResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerMembershipResource extends JsonResource
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
            'membershipPlanId' => $this->membership_plan_id,
            'membershipStartDate' => $this->membership_start_date,
            'membershipEndDate' => $this->membership_end_date,
            'status' => $this->status,
            'membershipPlan' => $this->whenLoaded('membershipPlan', function () {
                return new MembershipPlanResource($this->membershipPlan);
            }),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}

