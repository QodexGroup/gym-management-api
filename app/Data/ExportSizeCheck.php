<?php

namespace App\Data;

/**
 * Typed return object for the pre-export size check.
 *
 * Property names and their order are part of the API response: the frontend
 * reads tooLarge to decide between a direct download and an emailed report.
 */
class ExportSizeCheck
{
    public bool $tooLarge;
    public int $rowCount;
}
