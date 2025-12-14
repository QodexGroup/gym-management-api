<?php

namespace App\Services\Core;

use App\Models\Core\CustomerScans;
use App\Repositories\Core\CustomerScanRepository;
use App\Repositories\Core\CustomerFileRepository;
use Illuminate\Support\Facades\Log;

class CustomerScanService
{
    public function __construct(
        private CustomerScanRepository $customerScanRepository,
        private CustomerFileRepository $customerFileRepository,
    )
    {}

    /**
     * @param int $id
     *
     * @return array
     */
    public function deleteScan(int $id): array
    {
        try {
            // Get files directly using the repository to ensure we get them
            $files = $this->customerFileRepository->getFilesByFileable(CustomerScans::class, $id);
            $fileUrls = $files->pluck('file_url')->toArray();

            // Delete file records from database
            $this->customerFileRepository->deleteFilesByFileable(CustomerScans::class, $id);

            // Delete the scan record
            $this->customerScanRepository->deleteScan($id);

            return $fileUrls;
        } catch (\Throwable $th) {
            Log::error('Failed to delete scan: ' . $th->getMessage());
            throw $th; // Re-throw to let controller handle the error
        }
    }
}
