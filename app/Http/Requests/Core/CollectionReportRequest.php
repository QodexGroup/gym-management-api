<?php

namespace App\Http\Requests\Core;

use App\Dtos\Core\CollectionReportDto;
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
            'exportType' => ['nullable', 'string', 'in:pdf,xlsx'],
            'periodLabel' => ['nullable', 'string'],
        ]);
    }

    /**
     * Convert request to CollectionReportDto
     *
     * @return CollectionReportDto
     */
    public function toCollectionReportDto(): CollectionReportDto
    {
        $genericData = $this->getGenericData();
        $validated = $this->validated();

        $dto = new CollectionReportDto();
        $dto->setAccountId($genericData->userData->account_id);
        $dto->setDateFrom($validated['dateFrom']);
        $dto->setDateTo($validated['dateTo']);
        $dto->setExportType($validated['exportType'] ?? null);
        $dto->setPeriodLabel($validated['periodLabel'] ?? null);

        return $dto;
    }
}
