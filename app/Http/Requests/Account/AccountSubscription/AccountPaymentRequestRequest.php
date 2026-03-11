<?php

namespace App\Http\Requests\Account\AccountSubscription;

use App\Http\Requests\GenericRequest;
use Illuminate\Validation\Rule;

class AccountPaymentRequestRequest extends GenericRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'invoiceId' => [
                'required',
                'integer',
                Rule::exists('account_invoices', 'id'),
            ],
            'receiptUrl' => ['required', 'string', 'max:500'],
            'receiptFileName' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
