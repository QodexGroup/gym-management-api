<?php

namespace App\Http\Resources\Core;

use App\Http\Resources\Account\PtPackageResource;
use App\Http\Resources\Account\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class PtBookingResource extends JsonResource
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
            'ptPackageId' => $this->pt_package_id,
            'ptPackage' => $this->whenLoaded('ptPackage', function () {
                return new PtPackageResource($this->ptPackage);
            }),
            'customerId' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),
            'coachId' => $this->coach_id,
            'coach' => $this->whenLoaded('coach', function () {
                return new UserResource($this->coach);
            }),
            'classScheduleId' => $this->class_schedule_id,
            'bookingDate' => $this->booking_date,
            'bookingTime' => $this->booking_time,
            'duration' => $this->duration,
            'status' => $this->status,
            'bookingNotes' => $this->booking_notes,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
