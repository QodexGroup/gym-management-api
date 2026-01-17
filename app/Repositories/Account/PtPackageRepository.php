<?php

namespace App\Repositories\Account;

use App\Helpers\GenericData;
use App\Models\Account\PtPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PtPackageRepository
{
    /**
     * Get all PT packages
     *
     * @param GenericData $genericData
     * @return LengthAwarePaginator|Collection
     */
    public function getAllPtPackages(GenericData $genericData): LengthAwarePaginator|Collection
    {
        $query = PtPackage::where('account_id', $genericData->userData->account_id);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        // Check if pagination is requested
        if ($genericData->pageSize > 0) {
            return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
        }

        return $query->get();
    }

    /**
     * Get a PT package by ID
     *
     * @param int $id
     * @param int $accountId
     * @return PtPackage
     */
    public function findPtPackageById(int $id, int $accountId): PtPackage
    {
        return PtPackage::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }

    /**
     * Create a new PT package
     *
     * @param GenericData $genericData
     * @return PtPackage
     */
    public function createPtPackage(GenericData $genericData): PtPackage
    {
        // Ensure account_id is set in data
        $genericData->getData()->account_id = $genericData->userData->account_id;
        $genericData->syncDataArray();

        return PtPackage::create($genericData->data)->fresh();
    }

    /**
     * Update a PT package
     *
     * @param int $id
     * @param GenericData $genericData
     * @return PtPackage
     */
    public function updatePtPackage(int $id, GenericData $genericData): PtPackage
    {
        $package = $this->findPtPackageById($id, $genericData->userData->account_id);
        $package->update($genericData->data);
        return $package->fresh();
    }

    /**
     * Delete a PT package (soft delete)
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     */
    public function deletePtPackage(int $id, int $accountId): bool
    {
        $package = $this->findPtPackageById($id, $accountId);
        return $package->delete();
    }
}
