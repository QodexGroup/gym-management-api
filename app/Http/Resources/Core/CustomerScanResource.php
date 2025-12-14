<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Core\CustomerFileResource;

class CustomerScanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'scanType' => $this->scan_type,
            'scanDate' => $this->scan_date,
            'notes' => $this->notes,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'deletedAt' => $this->deleted_at,
            'files' => CustomerFileResource::collection($this->resource->getRelation('files')),
        ];
    }
}
