<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleSessionResource extends JsonResource
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
            'classScheduleId' => $this->class_schedule_id,
            'classSchedule' => $this->whenLoaded('classSchedule', function () {
                return new ClassScheduleResource($this->classSchedule);
            }),
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'attendanceCount' => $this->attendance_count,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
