<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;
use Illuminate\Validation\Rule;

class CustomerMembershipRequest extends GenericRequest
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
        return array_merge(parent::rules(), [
            'membershipPlanId' => [
                'required',
                'integer',
                'exists:tb_membership_plan,id',
            ],
            'membershipStartDate' => [
                'nullable',
                'date',
            ],
        ]);
    }
}

