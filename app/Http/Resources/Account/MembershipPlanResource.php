<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipPlanResource extends JsonResource
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
            'planName' => $this->plan_name,
            'price' => $this->price,
            'planPeriod' => $this->plan_period,
            'planInterval' => $this->plan_interval,
            'features' => $this->features,
            'activeMembersCount' => $this->active_members_count ?? 0,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}

