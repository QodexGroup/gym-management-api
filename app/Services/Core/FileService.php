<?php

namespace App\Services\Core;

use App\Models\Core\CustomerFiles;
use App\Models\Core\CustomerProgress;
use App\Models\Core\CustomerScans;
use App\Repositories\Core\CustomerFileRepository;
use App\Repositories\Core\CustomerProgressRepository;
use App\Repositories\Core\CustomerScanRepository;

class FileService
{
    public function __construct(
        private CustomerFileRepository $customerFileRepository,
        private CustomerProgressRepository $customerProgressRepository,
        private CustomerScanRepository $customerScanRepository,
    )
    {}

    /**
     * Create a file record for a fileable entity (progress or scan)
     *
     * @param string $fileableType The class name
     * @param int $fileableId The ID of the parent entity
     * @param array $validated Validated file data
     * @return CustomerFiles
     */
    public function createFile(string $fileableType, int $fileableId, array $validated): CustomerFiles
    {
        // Get the parent entity to retrieve customer_id
        $parent = match ($fileableType) {
            CustomerProgress::class => $this->customerProgressRepository->getProgressById($fileableId),
            CustomerScans::class => $this->customerScanRepository->getScanById($fileableId),
            default => throw new \InvalidArgumentException("Unsupported fileable type: {$fileableType}"),
        };

        $validated['fileableType'] = $fileableType;
        $validated['fileableId'] = $fileableId;
        $validated['customerId'] = $parent->customer_id;

        return $this->customerFileRepository->createFile($validated);
    }

    /**
     * Delete a file record and return its file URL
     *
     * @param int $id
     * @return array|null Returns array with fileUrl or null if file not found
     */
    public function deleteFile(int $id): ?array
    {
        $file = $this->customerFileRepository->getFileById($id);

        if (!$file) {
            return null;
        }

        $fileUrl = $file->file_url;
        $this->customerFileRepository->deleteFile($id);

        return ['fileUrl' => $fileUrl];
    }
}
