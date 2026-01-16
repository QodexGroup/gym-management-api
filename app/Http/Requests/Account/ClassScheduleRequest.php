<?php

namespace App\Http\Requests\Account;

use App\Constant\RecurringIntervalConstant;
use App\Constant\ScheduleTypeConstant;
use App\Http\Requests\GenericRequest;

class ClassScheduleRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'className' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'coachId' => ['required', 'integer', 'exists:users,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'duration' => ['required', 'integer', 'min:1'],
            'startDate' => ['required', 'date'],
            'scheduleType' => ['required', 'integer'],
            'recurringInterval' => ['nullable', 'string'],
            'numberOfSessions' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
