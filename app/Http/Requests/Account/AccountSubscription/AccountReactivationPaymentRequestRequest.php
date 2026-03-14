<?php

namespace App\Http\Requests\Account\AccountSubscription;

use App\Http\Requests\GenericRequest;

class AccountReactivationPaymentRequestRequest extends GenericRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'receiptUrl' => ['required', 'string', 'max:500'],
            'receiptFileName' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

