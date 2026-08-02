<?php

namespace App\Modules\Imports\Services;

use App\Modules\Imports\Data\ImportRowOutcome;
use App\Repositories\Core\CustomerRepository;
use App\Rules\ValidEmail;
use App\Modules\Imports\Contracts\ImportTypeHandler;
use App\Modules\Imports\Support\FieldDefinition;
use Illuminate\Support\Facades\Validator;
use App\Modules\Imports\Constants\ImportConstant;

/**
 * Import handler for gym clients (customers). Defines the mappable fields and
 * turns a single mapped row into a customer, skipping duplicates by name.
 */
class ClientImportHandler implements ImportTypeHandler
{
    public function __construct(private CustomerRepository $customerRepository)
    {
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return ImportConstant::TYPE_CLIENT;
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return 'Clients / Customers';
    }

    /**
     * @inheritDoc
     */
    public function description(): string
    {
        return 'Import gym clients from a CSV or Excel file. Map each column to a client field.';
    }

    /**
     * @inheritDoc
     */
    public function fields(): array
    {
        return [
            // Required fields (shown first in the mapping UI)
            new FieldDefinition('firstName', 'First Name', true, 'required|string|max:255', 'John'),
            new FieldDefinition('lastName', 'Last Name', true, 'required|string|max:255', 'Smith'),
            new FieldDefinition('phoneNumber', 'Phone Number', true, 'required|string|max:20', '09123456789'),
            new FieldDefinition('dateOfBirth', 'Date of Birth', true, 'required|date', '1990-05-14'),

            // Optional fields
            new FieldDefinition('gender', 'Gender', false, 'nullable|string|in:Male,Female,Other', 'Male'),
            new FieldDefinition('email', 'Email', false, 'nullable|string|max:255', 'john@email.com'),
            new FieldDefinition('address', 'Address', false, 'nullable|string', 'Cebu City'),
            new FieldDefinition('bloodType', 'Blood Type', false, 'nullable|string|max:10', 'O+'),
            new FieldDefinition('medicalNotes', 'Medical Notes', false, 'nullable|string', 'Asthma'),
            new FieldDefinition('allergies', 'Allergies', false, 'nullable|string', 'Peanuts'),
            new FieldDefinition('currentMedications', 'Current Medications', false, 'nullable|string', 'None'),
            new FieldDefinition('medicalConditions', 'Medical Conditions', false, 'nullable|string', 'Hypertension'),
            new FieldDefinition('doctorName', 'Doctor Name', false, 'nullable|string|max:255', 'Dr. Cruz'),
            new FieldDefinition('doctorPhone', 'Doctor Phone', false, 'nullable|string|max:20', '09170000000'),
            new FieldDefinition('insuranceProvider', 'Insurance Provider', false, 'nullable|string|max:255', 'Maxicare'),
            new FieldDefinition('insurancePolicyNumber', 'Insurance Policy Number', false, 'nullable|string|max:100', 'POL-12345'),
            new FieldDefinition('emergencyContactName', 'Emergency Contact Name', false, 'nullable|string|max:255', 'Jane Smith'),
            new FieldDefinition('emergencyContactRelationship', 'Emergency Contact Relationship', false, 'nullable|string|max:100', 'Spouse'),
            new FieldDefinition('emergencyContactPhone', 'Emergency Contact Phone', false, 'nullable|string|max:20', '09180000000'),
            new FieldDefinition('emergencyContactAddress', 'Emergency Contact Address', false, 'nullable|string', 'Cebu City'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function options(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function importRow(array $mapped, int $accountId, ?int $createdBy = null): ImportRowOutcome
    {
        $mapped = $this->normalize($mapped);

        $validator = Validator::make($mapped, $this->validationRules());
        if ($validator->fails()) {
            return ImportRowOutcome::failed($validator->errors()->toArray());
        }

        $data = $validator->validated();

        // Skip duplicates: match an existing client by full name (first + last),
        // regardless of phone or email.
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;
        if ($firstName && $lastName && $this->customerRepository->existsByName($accountId, $firstName, $lastName)) {
            return ImportRowOutcome::skipped("A client named {$firstName} {$lastName} already exists.");
        }

        $customer = $this->customerRepository->createFromImport($accountId, $data);

        return ImportRowOutcome::success((int) $customer->id);
    }

    /**
     * Build the per-row validation rule map from the field definitions.
     *
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        $rules = [];
        foreach ($this->fields() as $field) {
            $ruleSet = explode('|', $field->rules);
            // Swap the generic email string rule for the project's ValidEmail rule.
            if ($field->key === 'email') {
                $ruleSet = ['nullable', 'max:255', new ValidEmail()];
            }
            $rules[$field->key] = $ruleSet;
        }
        return $rules;
    }

    /**
     * Light normalization so common spreadsheet variants pass validation:
     * blanks become null, gender is title-cased, blood type upper-cased.
     *
     * @param array<string, mixed> $mapped
     * @return array<string, mixed>
     */
    private function normalize(array $mapped): array
    {
        foreach ($mapped as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $mapped[$key] = $value === '' ? null : $value;
            }
        }

        if (!empty($mapped['gender']) && is_string($mapped['gender'])) {
            $mapped['gender'] = ucfirst(strtolower($mapped['gender']));
        }

        if (!empty($mapped['bloodType']) && is_string($mapped['bloodType'])) {
            $mapped['bloodType'] = strtoupper(str_replace(' ', '', $mapped['bloodType']));
        }

        return $mapped;
    }
}
