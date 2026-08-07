<?php

namespace App\Modules\Imports\Constants;

class ImportConstant
{
    // Import job lifecycle statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    // Per-row result statuses
    const RESULT_SUCCESS = 'success';
    const RESULT_FAILED = 'failed';
    const RESULT_SKIPPED = 'skipped';

    // Supported import types (registry keys)
    const TYPE_CLIENT = 'client';

    // How often (in rows) the queue job flushes progress to the DB
    const PROGRESS_FLUSH_EVERY = 25;

    // Upload limits
    const MAX_FILE_SIZE_KB = 51200; // 50 MB
    const SUPPORTED_EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls'];
}
