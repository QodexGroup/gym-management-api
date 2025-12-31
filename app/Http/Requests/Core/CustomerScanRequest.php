<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

class CustomerScanRequest extends GenericRequest
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
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customerId' => ['required', 'integer'],
            'scanType' => ['required', 'string', 'in:inbody,styku'],
            'scanDate' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
