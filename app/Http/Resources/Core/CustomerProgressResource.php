<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Core\CustomerFileResource;

class CustomerProgressResource extends JsonResource
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
            'recordedBy' => $this->recorded_by,

            // Basic Measurements
            'weight' => $this->weight,
            'height' => $this->height,
            'bodyFatPercentage' => $this->body_fat_percentage,
            'bmi' => $this->bmi,

            // Body Measurements
            'chest' => $this->chest,
            'waist' => $this->waist,
            'hips' => $this->hips,
            'leftArm' => $this->left_arm,
            'rightArm' => $this->right_arm,
            'leftThigh' => $this->left_thigh,
            'rightThigh' => $this->right_thigh,
            'leftCalf' => $this->left_calf,
            'rightCalf' => $this->right_calf,

            // Body Composition
            'skeletalMuscleMass' => $this->skeletal_muscle_mass,
            'bodyFatMass' => $this->body_fat_mass,
            'totalBodyWater' => $this->total_body_water,
            'protein' => $this->protein,
            'minerals' => $this->minerals,
            'visceralFatLevel' => $this->visceral_fat_level,
            'basalMetabolicRate' => $this->basal_metabolic_rate,
            'dataSource' => $this->data_source,
            'customerScanId' => $this->customer_scan_id,
            'notes' => $this->notes,
            'recordedDate' => $this->recorded_date,
            'files' => CustomerFileResource::collection($this->resource->getRelation('files')),
            'scan' => $this->when(
                $this->resource->relationLoaded('scan') && ($scan = $this->resource->getRelation('scan'))?->relationLoaded('files'),
                fn() => CustomerFileResource::collection($scan->getRelation('files')),
                []
            ),
            'customer' => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),

            // Timestamps
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'deletedAt' => $this->deleted_at,
        ];
    }
}
