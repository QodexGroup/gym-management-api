<?php

namespace App\Http\Requests\Core;

use App\Constants\ExportTypeConstant;
use App\Constants\ReportTypeConstant;
use App\Http\Requests\Common\FilterDateRequest;

class EmailReportRequest extends FilterDateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'startDate' => ['required', 'date', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'reportType' => ['required', 'string', 'in:' . ReportTypeConstant::getValidationRule()],
            'format' => ['nullable', 'string', 'in:' . ExportTypeConstant::PDF . ',' . ExportTypeConstant::REQUEST_FORMAT_EXCEL],
            'dateRange' => ['nullable', 'string'],
        ]);
    }
}
