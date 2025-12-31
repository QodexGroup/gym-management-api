<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\GenericRequest;

class MembershipPlanRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'planName' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:1'],
            'planPeriod' => ['required', 'integer', 'min:1'],
            'planInterval' => ['required', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string'],
        ]);
    }
}

