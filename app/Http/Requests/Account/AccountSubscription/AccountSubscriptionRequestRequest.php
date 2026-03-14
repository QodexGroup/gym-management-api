<?php

namespace App\Http\Requests\Account\AccountSubscription;

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
        ]);
    }
}
