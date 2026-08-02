<?php

namespace App\Modules\Imports\Repositories;

use App\Modules\Imports\Constants\ImportConstant;
use App\Modules\Imports\Models\ImportResult;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for per-row import results (tb_import_results).
 */
class ImportResultRepository extends BaseRepository
{
    /**
     * Persist a single row result.
     *
     * @param array<string, mixed> $data
     * @return ImportResult
     */
    public function create(array $data): ImportResult
    {
        return ImportResult::create($data);
    }

    /**
     * Get failed results for a job, ordered by row number.
     *
     * @param int $importJobId
     * @param int $accountId
     * @return Collection
     */
    public function getFailedForJob(int $importJobId, int $accountId): Collection
    {
        return ImportResult::where('import_job_id', $importJobId)
            ->where('account_id', $accountId)
            ->where('status', ImportConstant::RESULT_FAILED)
            ->orderBy('row_number')
            ->get();
    }

    /**
     * Get all non-successful results (failed + skipped) for building a result file.
     *
     * @param int $importJobId
     * @return Collection
     */
    public function getUnsuccessfulForJob(int $importJobId): Collection
    {
        return ImportResult::where('import_job_id', $importJobId)
            ->where('status', '!=', ImportConstant::RESULT_SUCCESS)
            ->orderBy('row_number')
            ->get();
    }
}
