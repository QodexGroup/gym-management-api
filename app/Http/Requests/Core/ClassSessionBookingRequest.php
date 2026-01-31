<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

class ClassSessionBookingRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'sessionId' => ['sometimes', 'integer', 'exists:tb_class_schedule_sessions,id'],
            'customerId' => ['sometimes', 'integer', 'exists:tb_customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', 'string'],
        ]);
    }
}
