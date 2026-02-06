<?php

namespace App\Http\Requests\Core;

use App\Dtos\Core\SummaryReportDto;
use App\Http\Requests\GenericRequest;

class SummaryReportRequest extends GenericRequest
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
            'exportType' => ['nullable', 'string', 'in:pdf,xlsx'],
            'periodLabel' => ['nullable', 'string'],
        ]);
    }

    /**
     * Convert request to SummaryReportDto
     *
     * @return SummaryReportDto
     */
    public function toSummaryReportDto(): SummaryReportDto
    {
        $genericData = $this->getGenericData();
        $validated = $this->validated();

        $dto = new SummaryReportDto();
        $dto->setAccountId($genericData->userData->account_id);
        $dto->setDateFrom($validated['dateFrom']);
        $dto->setDateTo($validated['dateTo']);
        $dto->setExportType($validated['exportType'] ?? null);
        $dto->setPeriodLabel($validated['periodLabel'] ?? null);

        return $dto;
    }
}
