<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class CustomerScanRequest extends FormRequest
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
        return [
            'scanType' => ['required', 'string', 'in:inbody,styku'],
            'scanDate' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
