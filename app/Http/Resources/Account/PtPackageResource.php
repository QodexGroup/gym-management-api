<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PtPackageResource extends JsonResource
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
            'categoryId' => $this->category_id,
            'category' => $this->whenLoaded('category', fn() => new PtCategoryResource($this->category)),
            'packageName' => $this->package_name,
            'description' => $this->description,
            'numberOfSessions' => $this->number_of_sessions,
            'durationPerSession' => $this->duration_per_session,
            'price' => $this->price,
            'features' => $this->features,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
