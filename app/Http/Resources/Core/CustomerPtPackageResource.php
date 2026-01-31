<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\Account\PtPackageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPtPackageResource extends JsonResource
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
            'ptPackageId' => $this->pt_package_id,
            'startDate' => $this->start_date,
            'status' => $this->status,
            'numberOfSessionsRemaining' => $this->number_of_sessions_remaining,
            'ptPackage' => $this->whenLoaded('ptPackage', function () {
                return new PtPackageResource($this->ptPackage);
            }),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
