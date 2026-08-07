<?php

namespace App\Modules\Imports\Request;

use App\Http\Requests\GenericRequest;

class ImportExecuteRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'importJobId' => ['required', 'integer'],
            'columnMapping' => ['required', 'array'],
            'columnMapping.*' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
        ];
    }
}
