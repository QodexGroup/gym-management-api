<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'dateOfBirth' => ['nullable', 'date'],
            'photo' => ['nullable', 'string', 'max:500'],
            'phoneNumber' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'medicalNotes' => ['nullable', 'string'],
            'emergencyContactName' => ['nullable', 'string', 'max:255'],
            'emergencyContactPhone' => ['nullable', 'string', 'max:20'],
            'bloodType' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable', 'string'],
            'currentMedications' => ['nullable', 'string'],
            'medicalConditions' => ['nullable', 'string'],
            'doctorName' => ['nullable', 'string', 'max:255'],
            'doctorPhone' => ['nullable', 'string', 'max:20'],
            'insuranceProvider' => ['nullable', 'string', 'max:255'],
            'insurancePolicyNumber' => ['nullable', 'string', 'max:100'],
            'emergencyContactRelationship' => ['nullable', 'string', 'max:100'],
            'emergencyContactAddress' => ['nullable', 'string'],
        ];
    }
}

