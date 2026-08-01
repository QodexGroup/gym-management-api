<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

class CustomerPaymentRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => ['required', 'integer', 'exists:tb_customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paymentDate' => ['required', 'date'],
            'paymentMethod' => ['required', 'string'],
            'referenceNumber' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            // Optional receipt: R2 object path + its size (KB) for storage counting.
            'receiptUrl' => ['nullable', 'string', 'max:500'],
            'receiptSizeKb' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}


