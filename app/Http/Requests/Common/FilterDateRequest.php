<?php

namespace App\Http\Requests\Common;

use App\Http\Requests\GenericRequest;

class FilterDateRequest extends GenericRequest
{

     /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'startDate' => ['nullable', 'date', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ];
    }
}
