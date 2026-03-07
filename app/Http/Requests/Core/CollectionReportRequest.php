<?php

namespace App\Http\Requests\Core;

use App\Constants\ExportTypeConstant;
use App\Http\Requests\Common\FilterDateRequest;

class CollectionReportRequest extends FilterDateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'startDate' => ['required', 'date', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'exportType' => ['nullable', 'string', 'in:' . ExportTypeConstant::PDF . ',' . ExportTypeConstant::EXCEL],
            'periodLabel' => ['nullable', 'string'],
        ]);
    }
}
