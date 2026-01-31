<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\GenericRequest;

class ClassScheduleSessionRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->routeIs('class-schedule-sessions.update') || $this->isMethod('put')) {
            $rules['startTime'] = ['required', 'date'];
            $rules['endTime'] = ['nullable', 'date', 'after:startTime'];
            $rules['duration'] = ['nullable', 'integer', 'min:1']; // Duration in minutes
        }

        return $rules;
    }
}
