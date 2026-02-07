<?php

namespace App\Modules\CollectionReport;

use App\Helpers\GenericData;
use Illuminate\Database\Eloquent\Collection;

interface CollectionReportExportInterface
{
    /**
     * @param GenericData $genericData
     * @param Collection $collectionData
     *
     * @return mixed
     */
    public function export(GenericData $genericData, Collection $collectionData);
}
