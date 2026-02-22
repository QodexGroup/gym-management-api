<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\GenericRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequestRequest extends GenericRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'subscriptionPlanId' => [
                'required',
                'integer',
                Rule::exists('platform_subscription_plans', 'id')->where('is_trial', false),
            ],
            'receiptUrl' => ['required', 'string', 'max:500'],
            'receiptFileName' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
