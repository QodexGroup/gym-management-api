<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleResource extends JsonResource
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
            'className' => $this->class_name,
            'description' => $this->description,
            'coachId' => $this->coach_id,
            'coach' => $this->whenLoaded('coach', fn() => new CoachResource($this->coach)),
            'classType' => $this->class_type,
            'capacity' => $this->capacity,
            'duration' => $this->duration,
            'startDate' => $this->start_date,
            'scheduleType' => $this->schedule_type,
            'recurringInterval' => $this->recurring_interval,
            'numberOfSessions' => $this->number_of_sessions,
            'sessions' => $this->whenLoaded('sessions', fn() => ClassScheduleSessionResource::collection($this->sessions)),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
