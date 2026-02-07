<?php

namespace App\Http\Requests\Core;

use App\Constants\ExportTypeConstant;
use App\Constants\ReportTypeConstant;
use App\Http\Requests\GenericRequest;

class EmailReportRequest extends GenericRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'reportType' => ['required', 'string', 'in:' . ReportTypeConstant::getValidationRule()],
            'format' => ['nullable', 'string', 'in:' . ExportTypeConstant::PDF . ',' . ExportTypeConstant::REQUEST_FORMAT_EXCEL],
            'dateRange' => ['nullable', 'string'],
            'dateFrom' => ['required', 'string', 'date'],
            'dateTo' => ['required', 'string', 'date', 'after_or_equal:dateFrom'],
        ]);
    }

}
