<?php

namespace App\Constants;

class ReportConstant
{
    /**
     * Maximum number of rows a report may contain before it must be delivered
     * by email instead of being exported directly (PDF/Excel) in the browser.
     */
    const MAX_EXPORT_ROWS = 200;
}
