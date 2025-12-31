<?php

namespace App\Services\Core;

use App\Models\Core\CustomerProgress;
use App\Repositories\Core\CustomerProgressRepository;
use App\Repositories\Core\CustomerFileRepository;
use Illuminate\Support\Facades\Log;

class CustomerProgressService
{
    public function __construct(
        private CustomerProgressRepository $customerProgressRepository,
        private CustomerFileRepository $customerFileRepository,
    )
    {}
    /**
     * @param int $id
     * @param int $accountId
     *
     * @return array
     */
    public function deleteProgress(int $id, int $accountId): array
    {
        try {
            // Get files directly using the repository to ensure we get them
            $files = $this->customerFileRepository->getFilesByFileable(CustomerProgress::class, $id, $accountId);
            $fileUrls = $files->pluck('file_url')->toArray();

            // Delete file records from database
            $this->customerFileRepository->deleteFilesByFileable(CustomerProgress::class, $id, $accountId);

            // Delete the progress record
            $this->customerProgressRepository->deleteProgress($id, $accountId);

            return $fileUrls;
        } catch (\Throwable $th) {
            Log::error('Failed to delete progress: ' . $th->getMessage());
            throw $th; // Re-throw to let controller handle the error
        }
    }
}
