<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            'balance' => $this->balance,
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
            'currentMembership' => $this->whenLoaded('currentMembership', function () {
                return new CustomerMembershipResource($this->currentMembership);
            }),
            'currentTrainer' => $this->getCurrentTrainerData(),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'deletedAt' => $this->deleted_at,
        ];
    }

    /**
     * Get current trainer data, checking pivot table if relationship is empty
     *
     * @return array|null|TrainerResource
     */
    private function getCurrentTrainerData()
    {
        // Check if currentTrainer relationship is loaded and has data
        if ($this->relationLoaded('currentTrainer') && $this->currentTrainer->isNotEmpty()) {
            return new TrainerResource($this->currentTrainer->first());
        }

        // Check pivot table directly for trainer_id = 1
        $hasTrainer = DB::table('tb_customer_trainor')
            ->where('customer_id', $this->id)
            ->where('trainer_id', 1)
            ->exists();

        if ($hasTrainer) {
            return [
                'id' => 1,
                'name' => 'Jomilen Dela Torre',
                'email' => 'jomilen@example.com',
            ];
        }

        return null;
    }
}

