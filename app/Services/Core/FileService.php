<?php

namespace App\Services\Core;

use App\Helpers\GenericData;
use App\Models\Core\CustomerFiles;
use App\Models\Core\CustomerProgress;
use App\Models\Core\CustomerScans;
use App\Repositories\Core\CustomerFileRepository;
use App\Repositories\Core\CustomerProgressRepository;
use App\Repositories\Core\CustomerScanRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class FileService
{
    /**
     * @param CustomerFileRepository $customerFileRepository
     * @param CustomerProgressRepository $customerProgressRepository
     * @param CustomerScanRepository $customerScanRepository
     * @param StorageService $storageService
     */
    public function __construct(
        private CustomerFileRepository $customerFileRepository,
        private CustomerProgressRepository $customerProgressRepository,
        private CustomerScanRepository $customerScanRepository,
        private StorageService $storageService,
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

        $file = $this->customerFileRepository->createFile($genericData);

        // Keep the account's live storage counter in step with the new file
        // (size verified against R2 where available).
        $this->storageService->registerNewFile(
            (int) $genericData->userData->account_id,
            $file->file_url,
            (float) $file->file_size,
        );

        return $file;
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
        $fileSizeKb = (float) $file->file_size;
        $this->customerFileRepository->deleteFile($id, $accountId);

        // Release the freed space from the account's live storage counter.
        $this->storageService->decrementUsage($accountId, $fileSizeKb);

        // Delete from R2 storage
        try {
            Storage::disk('r2')->delete($fileUrl);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete file from R2', [
                'path' => $fileUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return ['fileUrl' => $fileUrl];
    }
}
