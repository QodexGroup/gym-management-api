<?php

namespace App\Repositories\Core;

use App\Helpers\GenericData;
use App\Models\Core\CustomerFiles;
use Illuminate\Support\Collection;

class CustomerFileRepository
{
    /**
     * Create a new file record
     *
     * @param GenericData $genericData
     * @return CustomerFiles
     */
    public function createFile(GenericData $genericData): CustomerFiles
    {
        $data = $genericData->data;
        $data['account_id'] = $genericData->userData->account_id;
        $data['uploaded_by'] = $genericData->userData->id;
        return CustomerFiles::create($data);
    }

    /**
     * Get a file by ID
     *
     * @param int $id
     * @param int $accountId
     * @return CustomerFiles|null
     */
    public function getFileById(int $id, int $accountId): ?CustomerFiles
    {
        return CustomerFiles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();
    }

    /**
     * Delete a file record
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function deleteFile(int $id, int $accountId): bool
    {
        return CustomerFiles::where('id', $id)
            ->where('account_id', $accountId)
            ->delete();
    }

    /**
     * @param string $fileableType
     * @param int $fileableId
     * @param int $accountId
     *
     * @return bool
     */
    public function deleteFilesByFileable(string $fileableType, int $fileableId, int $accountId): bool
    {
        return CustomerFiles::where('fileable_type', $fileableType)
            ->where('fileable_id', $fileableId)
            ->where('account_id', $accountId)
            ->delete();
    }

    /**
     * Get all files for a customer
     *
     * @param int $customerId
     * @param int $accountId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFilesByCustomerId(GenericData $genericData): Collection
    {
        return CustomerFiles::where('customer_id', $genericData->customerId)
            ->where('account_id', $genericData->userData->account_id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get files by fileable (polymorphic relationship)
     *
     * @param string $fileableType - Can be class name (CustomerScans::class) or full namespace string
     * @param int $fileableId
     * @param int $accountId
     * @return Collection
     */
    public function getFilesByFileable(string $fileableType, int $fileableId, int $accountId): Collection
    {
        return CustomerFiles::where('fileable_type', $fileableType)
            ->where('fileable_id', $fileableId)
            ->where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

