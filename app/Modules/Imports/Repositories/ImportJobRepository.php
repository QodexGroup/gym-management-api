<?php

namespace App\Modules\Imports\Repositories;

use App\Helpers\GenericData;
use App\Modules\Imports\Models\ImportJob;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Data access for import jobs (tb_import_jobs).
 */
class ImportJobRepository extends BaseRepository
{
    /**
     * Create a new import job.
     *
     * @param array<string, mixed> $data
     * @return ImportJob
     */
    public function create(array $data): ImportJob
    {
        return ImportJob::create($data)->fresh();
    }

    /**
     * Find an import job scoped to an account.
     *
     * @param int $id
     * @param int $accountId
     * @return ImportJob|null
     */
    public function findForAccount(int $id, int $accountId): ?ImportJob
    {
        return ImportJob::where('id', $id)->where('account_id', $accountId)->first();
    }

    /**
     * Update an import job and return the fresh model.
     *
     * @param ImportJob $job
     * @param array<string, mixed> $attributes
     * @return ImportJob
     */
    public function update(ImportJob $job, array $attributes): ImportJob
    {
        $job->update($attributes);
        return $job->fresh();
    }

    /**
     * Paginated import history for an account, newest first.
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getHistory(GenericData $genericData): LengthAwarePaginator
    {
        $query = ImportJob::where('account_id', $genericData->userData->account_id);

        if (!empty($genericData->filters['importType'])) {
            $query->where('import_type', $genericData->filters['importType']);
        }

        $query->orderBy('created_at', 'desc');

        return $this->paginateWithGenericData($query, $genericData);
    }
}
