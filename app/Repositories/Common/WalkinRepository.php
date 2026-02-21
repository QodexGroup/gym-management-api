<?php

namespace App\Repositories\Common;

use App\Constants\WalkinCustomerConstant;
use App\Helpers\GenericData;
use Illuminate\Support\Carbon;
use App\Models\Common\Walkin;
use App\Models\Common\WalkinCustomer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class WalkinRepository
{

    /**
     * @param GenericData $genericData
     *
     * @return Walkin|null
     */
    public function getWalkin(GenericData $genericData): ?Walkin
    {
        $walkin = Walkin::where('account_id', $genericData->userData->account_id)
            ->whereDate('date', Carbon::today())
            ->first();

        return $walkin;
    }

        /**
     * @param int $walkinId
     * @param GenericData $genericData
     *
     * @return LengthAwarePaginator
     */
    public function getPaginatedWalkinCustomers(int $walkinId, GenericData $genericData): LengthAwarePaginator
    {
        $query = WalkinCustomer::where('walkin_id', $walkinId);

        $genericData->applyRelations($query);
        $genericData->applyFilters($query);
        $genericData->applySorts($query);

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * @param int $customerId
     * @param GenericData $genericData
     *
     * @return LengthAwarePaginator
     */
    public function getPaginatedWalkinByCustomer(int $customerId, GenericData $genericData): LengthAwarePaginator
    {
        $query = WalkinCustomer::where('customer_id', $customerId);

        $genericData->applyRelations($query);
        $genericData->applyFilters($query);
        $genericData->applySorts($query);

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * @param int $walkinId
     * @param GenericData $genericData
     *
     * @return WalkinCustomer|null
     */
    public function getWalkinCustomer(int $walkinId, GenericData $genericData): ?WalkinCustomer
    {
        return WalkinCustomer::where('walkin_id', $walkinId)
            ->where('customer_id', $genericData->getData()->customerId)
            ->first();
    }

    /**
     * @param GenericData $genericData
     *
     * @return Walkin
     */
    public function createWalkin(GenericData $genericData): Walkin
    {
        // Ensure account_id is set in data
        $genericData->getData()->account_id = $genericData->userData->account_id;
        $genericData->getData()->created_by = $genericData->userData->id;
        $genericData->getData()->updated_by = $genericData->userData->id;
        $genericData->getData()->date = Carbon::today();
        $genericData->syncDataArray();

        return Walkin::create($genericData->data)->fresh();
    }

    /**
     * @param int $walkinId
     * @param GenericData $genericData
     *
     * @return WalkinCustomer
     */
    public function createWalkinCustomer(int $walkinId, GenericData $genericData): WalkinCustomer
    {
        $genericData->getData()->walkin_id = $walkinId;
        $genericData->getData()->check_in_time = Carbon::now();
        $genericData->getData()->status = WalkinCustomerConstant::INSIDE_STATUS;
        $genericData->syncDataArray();

        return WalkinCustomer::create($genericData->data)->fresh();
    }

    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return WalkinCustomer
     */
    public function checkOutWalkinCustomer(int $id, GenericData $genericData): WalkinCustomer
    {
        $walkinCustomer = WalkinCustomer::where('id', $id)->firstOrFail();

        $walkinCustomer->check_out_time = Carbon::now();
        $walkinCustomer->status = WalkinCustomerConstant::OUTSIDE_STATUS;
        $walkinCustomer->save();

        return $walkinCustomer->fresh();
    }

    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return WalkinCustomer
     */
    public function cancelWalkinCustomer(int $id, GenericData $genericData): WalkinCustomer
    {
        $walkinCustomer = WalkinCustomer::where('id', $id)->firstOrFail();

        $walkinCustomer->status = WalkinCustomerConstant::CANCELLED_STATUS;
        $walkinCustomer->save();

        return $walkinCustomer->fresh();
    }

}
