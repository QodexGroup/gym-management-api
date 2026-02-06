<?php

namespace App\Modules\CollectionReport;

use App\Dtos\Core\CollectionReportDto;
use Illuminate\Database\Eloquent\Collection;

interface CollectionReportExportInterface
{
    /**
     * @param CollectionReportDto $collectionReportDto
     * @param Collection $collectionData
     *
     * @return mixed
     */
    public function export(CollectionReportDto $collectionReportDto, Collection $collectionData);
}
