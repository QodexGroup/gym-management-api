<?php

namespace App\Http\Requests\Account\AccountSubscription;

use App\Constant\AccountPaymentTypeConstant;
use App\Http\Requests\GenericRequest;
use Illuminate\Validation\Rule;

class AccountSubscriptionRequestRequest extends GenericRequest
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
            'subscriptionPlanId' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id')->where('is_trial', false),
            ],
            'paymentType' => ['nullable', 'string', Rule::in(AccountPaymentTypeConstant::values())],
            // Required only when we create a standalone "upgrade payment" (i.e. upgrade during trial).
            // Stored as a Firebase storage path (not a full URL).
            'receiptUrl' => ['nullable', 'string', 'max:500'],
            'receiptFileName' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
