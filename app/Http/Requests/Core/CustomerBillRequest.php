<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerBillRequest extends FormRequest
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
            'customerId' => ['required', 'integer', 'exists:tb_customers,id'],
            'grossAmount' => ['required', 'numeric', 'min:0'],
            'discountPercentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'netAmount' => ['nullable', 'numeric', 'min:0'],
            'paidAmount' => ['nullable', 'numeric', 'min:0'],
            'billDate' => ['required', 'date'],
            'billStatus' => ['nullable', 'string', Rule::in(['paid', 'partial', 'active'])],
            'billType' => ['required', 'string', 'max:255'],
            'membershipPlanId' => ['nullable','integer'],
            'customService' => ['nullable','string','max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customerId.required' => 'Customer ID is required.',
            'customerId.exists' => 'The selected customer does not exist.',
            'grossAmount.required' => 'Gross amount is required.',
            'grossAmount.numeric' => 'Gross amount must be a number.',
            'grossAmount.min' => 'Gross amount must be at least 0.',
            'discountPercentage.numeric' => 'Discount percentage must be a number.',
            'discountPercentage.min' => 'Discount percentage must be at least 0.',
            'discountPercentage.max' => 'Discount percentage cannot exceed 100.',
            'netAmount.numeric' => 'Net amount must be a number.',
            'netAmount.min' => 'Net amount must be at least 0.',
            'paidAmount.numeric' => 'Paid amount must be a number.',
            'paidAmount.min' => 'Paid amount must be at least 0.',
            'billDate.required' => 'Bill date is required.',
            'billDate.date' => 'Bill date must be a valid date.',
            'billStatus.in' => 'Bill status must be one of: paid, partial, active.',
            'billType.required' => 'Bill type is required.',
        ];
    }
}

