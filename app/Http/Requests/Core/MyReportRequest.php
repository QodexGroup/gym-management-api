<?php

namespace App\Http\Requests\Core;

use App\Http\Requests\Common\FilterDateRequest;

/**
 * Date-range filter request for the coach "My Collection" / "My Revenue" reports.
 * Mirrors the admin report filter requests (validated start/end date range).
 */
class MyReportRequest extends FilterDateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'startDate' => ['required', 'date', 'date_format:Y-m-d'],
            'endDate' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:startDate'],
        ]);
    }
}
