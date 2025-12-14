<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

class CustomerFileRequest extends FormRequest
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
        return [
            'customerId' => ['required', 'integer'],
            'remarks' => ['required', 'string'],
            'fileName' => ['required', 'string', 'max:255'],
            'fileUrl' => ['required', 'string', 'max:500'], // Path format: {accountId}/{customerId}/filename
            'fileSize' => ['nullable', 'numeric', 'min:0'], // File size in KB (numeric)
            'mimeType' => ['nullable', 'string', 'max:100'],
            'fileDate' => ['required', 'date'],
        ];
    }
}
