<?php

namespace App\Http\Requests\Account\AccountSubscription;

use App\Http\Requests\GenericRequest;

class AccountBillingInformationRequest extends GenericRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'legalName' => ['nullable', 'string', 'max:255'],
            'businessName' => ['nullable', 'string', 'max:255'],
            'billingEmail' => ['nullable', 'email', 'max:255'],
            'taxId' => ['nullable', 'string', 'max:100'],
            'vatNumber' => ['nullable', 'string', 'max:100'],
            'addressLine1' => ['nullable', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'stateProvince' => ['nullable', 'string', 'max:100'],
            'postalCode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);
    }

    /**
     * Get validated data as snake_case for model fillable.
     */
    public function getBillingDataForService(): array
    {
        $validated = $this->validated();
        $map = [
            'legalName' => 'legal_name',
            'businessName' => 'business_name',
            'billingEmail' => 'billing_email',
            'taxId' => 'tax_id',
            'vatNumber' => 'vat_number',
            'addressLine1' => 'address_line_1',
            'addressLine2' => 'address_line_2',
            'city' => 'city',
            'stateProvince' => 'state_province',
            'postalCode' => 'postal_code',
            'country' => 'country',
        ];
        $result = [];
        foreach ($map as $camel => $snake) {
            if (array_key_exists($camel, $validated)) {
                $result[$snake] = $validated[$camel];
            }
        }
        return $result;
    }
}
