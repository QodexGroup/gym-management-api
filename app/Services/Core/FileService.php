<?php

namespace App\Services\Core;

use App\Helpers\GenericData;
use App\Models\Core\CustomerFiles;
use App\Models\Core\CustomerProgress;
use App\Models\Core\CustomerScans;
use App\Repositories\Core\CustomerFileRepository;
use App\Repositories\Core\CustomerProgressRepository;
use App\Repositories\Core\CustomerScanRepository;
use InvalidArgumentException;

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
     * @param GenericData $genericData
     * @return CustomerFiles
     */
    public function createFile(string $fileableType, int $fileableId, GenericData $genericData): CustomerFiles
    {
        // Get the parent entity to retrieve customer_id and account_id
        $parent = match ($fileableType) {
            CustomerProgress::class => $this->customerProgressRepository->getProgressById($fileableId, $genericData->userData->account_id),
            CustomerScans::class => $this->customerScanRepository->getScanById($fileableId, $genericData->userData->account_id),
            default => throw new InvalidArgumentException("Unsupported fileable type: {$fileableType}"),
        };

        $genericData->data['fileable_type'] = $fileableType;
        $genericData->data['fileable_id'] = $fileableId;
        $genericData->data['customer_id'] = $parent->customer_id;

        return $this->customerFileRepository->createFile($genericData);
    }

    /**
     * Delete a file record and return its file URL
     *
     * @param int $id
     * @param int $accountId
     * @return array|null Returns array with fileUrl or null if file not found
     */
    public function deleteFile(int $id, int $accountId): ?array
    {
        $file = $this->customerFileRepository->getFileById($id, $accountId);

        if (!$file) {
            return null;
        }

        $fileUrl = $file->file_url;
        $this->customerFileRepository->deleteFile($id, $accountId);

        return ['fileUrl' => $fileUrl];
    }
}
