<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'gender' => $this->gender,
            'dateOfBirth' => $this->date_of_birth,
            'photo' => $this->photo,
            'phoneNumber' => $this->phone_number,
            'email' => $this->email,
            'address' => $this->address,
            'medicalNotes' => $this->medical_notes,
            'emergencyContactName' => $this->emergency_contact_name,
            'emergencyContactPhone' => $this->emergency_contact_phone,
            'bloodType' => $this->blood_type,
            'allergies' => $this->allergies,
            'currentMedications' => $this->current_medications,
            'medicalConditions' => $this->medical_conditions,
            'doctorName' => $this->doctor_name,
            'doctorPhone' => $this->doctor_phone,
            'insuranceProvider' => $this->insurance_provider,
            'insurancePolicyNumber' => $this->insurance_policy_number,
            'emergencyContactRelationship' => $this->emergency_contact_relationship,
            'emergencyContactAddress' => $this->emergency_contact_address,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'deletedAt' => $this->deleted_at,
        ];
    }
}

