<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;
use Illuminate\Validation\Rule;

class ClassSessionBookingRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Add booking-specific rules based on route
        $rules['sessionId'] = ['required', 'integer', 'exists:tb_class_schedule_sessions,id'];
        $rules['customerId'] = ['required', 'integer', 'exists:tb_customers,id'];
        $rules['notes'] = ['nullable', 'string', 'max:1000'];

        return $rules;
    }
}
