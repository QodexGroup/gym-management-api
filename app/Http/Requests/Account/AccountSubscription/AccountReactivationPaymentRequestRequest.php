<?php

namespace App\Http\Requests\Account\AccountSubscription;

use App\Constant\AccountPaymentTypeConstant;
use App\Http\Requests\GenericRequest;
use Illuminate\Validation\Rule;

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
            'paymentType' => ['required', 'string', Rule::in(AccountPaymentTypeConstant::values())],
            'receiptUrl' => ['required', 'string', 'max:500'],
            'receiptFileName' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

