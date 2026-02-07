<?php

namespace App\Http\Requests\Core;

use App\Constants\ExportTypeConstant;
use App\Http\Requests\GenericRequest;

class CollectionReportRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'dateFrom' => ['required', 'string', 'date'],
            'dateTo' => ['required', 'string', 'date', 'after_or_equal:dateFrom'],
            'exportType' => ['nullable', 'string', 'in:' . ExportTypeConstant::PDF . ',' . ExportTypeConstant::EXCEL],
            'periodLabel' => ['nullable', 'string'],
        ]);
    }

}
