<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\GenericRequest;

class PtPackageRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'categoryId' => ['required', 'integer', 'exists:tb_pt_categories,id'],
            'packageName' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'numberOfSessions' => ['required', 'integer', 'min:1'],
            'durationPerSession' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string'],
        ]);
    }
}
