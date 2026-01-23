<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\Account\ClassScheduleSessionResource;
use App\Http\Resources\Core\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassSessionBookingResource extends JsonResource
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
            'sessionId' => $this->class_schedule_session_id,
            'classScheduleSession' => $this->whenLoaded('classScheduleSession', function () {
                return new ClassScheduleSessionResource($this->classScheduleSession);
            }),
            'customer' => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
