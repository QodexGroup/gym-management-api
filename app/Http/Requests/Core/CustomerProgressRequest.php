<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

class CustomerProgressRequest extends GenericRequest
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
        return array_merge(parent::rules(), [
            // Required fields
            'customerId' => ['required', 'integer'],
            'recordedDate' => ['required', 'date'],
            'dataSource' => ['required', 'string', 'in:manual,inbody,styku'],

            // Basic Measurements (all nullable)
            'weight' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'bodyFatPercentage' => ['nullable', 'numeric'],
            'bmi' => ['nullable', 'numeric'],

            // Body Measurements (all nullable, in cm)
            'chest' => ['nullable', 'numeric'],
            'waist' => ['nullable', 'numeric'],
            'hips' => ['nullable', 'numeric'],
            'leftArm' => ['nullable', 'numeric'],
            'rightArm' => ['nullable', 'numeric'],
            'leftThigh' => ['nullable', 'numeric'],
            'rightThigh' => ['nullable', 'numeric'],
            'leftCalf' => ['nullable', 'numeric'],
            'rightCalf' => ['nullable', 'numeric'],

            // Body Composition (all nullable)
            'skeletalMuscleMass' => ['nullable', 'numeric'],
            'bodyFatMass' => ['nullable', 'numeric'],
            'totalBodyWater' => ['nullable', 'numeric'],
            'protein' => ['nullable', 'numeric'],
            'minerals' => ['nullable', 'numeric'],
            'visceralFatLevel' => ['nullable', 'numeric'],
            'basalMetabolicRate' => ['nullable', 'numeric'],

            // Notes
            'notes' => ['nullable', 'string'],
            'customerScanId' => ['nullable', 'integer', 'exists:tb_customer_scans,id'],
        ]);
    }
}
