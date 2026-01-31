<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\GenericRequest;

class CheckExportSizeRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'reportType' => ['required', 'string', 'in:collection,expense,summary'],
            'dateFrom' => ['required', 'string', 'date'],
            'dateTo' => ['required', 'string', 'date', 'after_or_equal:dateFrom'],
        ]);
    }
}
