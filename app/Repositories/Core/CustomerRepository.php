<?php

namespace App\Repositories\Core;

use App\Models\Core\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    /**
     * Get all customers for account_id = 1 with pagination (50 per page)
     *
     * @return LengthAwarePaginator
     */
    public function getAll(): LengthAwarePaginator
    {
        return Customer::where('account_id', 1)
            ->orderBy('created_at', 'desc')
            ->paginate(50);
    }

    /**
     * Create a new customer
     *
     * @param array $data
     * @return Customer
     */
    public function create(array $data): Customer
    {
        // Set account_id to 1 by default
        $data['account_id'] = 1;
        return Customer::create($data);
    }

    /**
     * @param int $id
     *
     * @return Customer
     */
    public function getById(int $id): Customer
    {
        return Customer::where('account_id', 1)->findOrFail($id);
    }

    /**
     * Update a customer
     *
     * @param int $id
     * @param array $data
     * @return Customer
     */
    public function update(int $id, array $data): Customer
    {
        $customer = Customer::where('account_id', 1)->findOrFail($id);
        $customer->update($data);
        return $customer->fresh();
    }

    /**
     * Delete a customer (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $customer = Customer::where('account_id', 1)->findOrFail($id);
        return $customer->delete();
    }
}

