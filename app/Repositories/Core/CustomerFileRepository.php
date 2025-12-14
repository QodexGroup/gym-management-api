<?php

namespace App\Repositories\Core;

use App\Models\Core\CustomerFiles;
use Illuminate\Support\Collection;

class CustomerFileRepository
{
    /**
     * Create a new file record
     *
     * @param array $data
     * @return CustomerFiles
     */
    public function createFile(array $data): CustomerFiles
    {
        // Set account_id to 1 by default
        $data['accountId'] = 1;

        return CustomerFiles::create($data);
    }

    /**
     * Get a file by ID
     *
     * @param int $id
     * @return CustomerFiles|null
     */
    public function getFileById(int $id): ?CustomerFiles
    {
        return CustomerFiles::where('id', $id)
            ->where('account_id', 1)
            ->first();
    }

    /**
     * Delete a file record
     *
     * @param int $id
     * @return bool
     */
    public function deleteFile(int $id): bool
    {
        return CustomerFiles::where('id', $id)
            ->where('account_id', 1)
            ->delete();
    }

    /**
     * @param string $fileableType
     * @param int $fileableId
     *
     * @return bool
     */
    public function deleteFilesByFileable(string $fileableType, int $fileableId): bool
    {
        return CustomerFiles::where('fileable_type', $fileableType)
            ->where('fileable_id', $fileableId)
            ->where('account_id', 1)
            ->delete();
    }

    /**
     * Get all files for a customer
     *
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFilesByCustomerId(int $customerId)
    {
        return CustomerFiles::where('customer_id', $customerId)
            ->where('account_id', 1)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get files by fileable (polymorphic relationship)
     *
     * @param string $fileableType - Can be class name (CustomerScans::class) or full namespace string
     * @param int $fileableId
     * @return Collection
     */
    public function getFilesByFileable(string $fileableType, int $fileableId): Collection
    {
        return CustomerFiles::where('fileable_type', $fileableType)
            ->where('fileable_id', $fileableId)
            ->where('account_id', 1)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

