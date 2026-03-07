<?php

namespace App\Http\Requests\Core;

use App\Constants\ReportTypeConstant;
use App\Http\Requests\Common\FilterDateRequest;

class CheckExportSizeRequest extends FilterDateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'startDate' => ['required', 'date', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'reportType' => ['required', 'string', 'in:' . ReportTypeConstant::getValidationRule()],
        ]);
    }
}
