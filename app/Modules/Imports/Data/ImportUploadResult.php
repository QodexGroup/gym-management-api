<?php

namespace App\Modules\Imports\Data;

/**
 * Payload returned to the client after a file is uploaded and parsed: the new
 * job id, the detected column headers, the row count, and the field/option
 * definitions the user maps against.
 */
class ImportUploadResult
{
    public int $importJobId;
    /** @var array<int, string> */
    public array $fileHeaders = [];
    public int $totalRows = 0;
    /** @var array<int, array<string, mixed>> */
    public array $importFields = [];
    /** @var array<int, array<string, mixed>> */
    public array $importOptions = [];
}
