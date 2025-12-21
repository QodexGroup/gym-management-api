<?php

namespace App\Repositories\Account;

use App\Constant\UserStatusConstant;
use App\Helpers\GenericData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class UsersRepository
{
    /**
     * @param GenericData $genericData
     * @return LengthAwarePaginator
     */
    public function getAllUsers(GenericData $genericData): LengthAwarePaginator
    {
        $query = User::where('account_id', $genericData->userData->account_id);

        // Apply relations, filters, and sorts using GenericData methods
        $query = $genericData->applyRelations($query, ['permissions']);
        $query = $genericData->applyFilters($query);
        $query = $genericData->applySorts($query);

        return $query->paginate($genericData->pageSize, ['*'], 'page', $genericData->page);
    }

    /**
     * Check if user exists by Firebase UID (including soft-deleted)
     *
     * @param string $firebaseUid
     * @param int $accountId
     * @return User|null
     */
    public function findUserByFirebaseUid(string $firebaseUid, int $accountId): ?User
    {
        return User::withTrashed()
            ->where('firebase_uid', $firebaseUid)
            ->where('account_id', $accountId)
            ->first();
    }

    /**
     * Find user by ID and account ID
     *
     * @param int $id
     * @param int $accountId
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findUserById(int $id, int $accountId): User
    {
        return User::where('id', $id)
            ->where('account_id', $accountId)
            ->firstOrFail();
    }

    /**
     * @param GenericData $genericData
     *
     * @return User
     */
    public function createUser(GenericData $genericData): User
    {
        return User::create($genericData->data)->fresh();
    }

    /**
     * @param int $id
     * @param GenericData $genericData
     *
     * @return User
     */
    public function updateUser(int $id, GenericData $genericData): User
    {
        $user = User::where('id', $id)
            ->where('account_id', $genericData->userData->account_id)
            ->firstOrFail();
        $user->update($genericData->data);
        return $user->fresh();
    }

    /**
     * @param int $id
     * @param int $accountId
     *
     * @return bool
     */
    public function deactivateUser(int $id, int $accountId): bool
    {
        return User::where('id', $id)
        ->where('account_id', $accountId)
        ->update(['status' => UserStatusConstant::DEACTIVATED]);
    }

    /**
     * Delete a user (soft delete)
     * Note: Once deleted, users cannot be restored
     *
     * @param int $id
     * @param int $accountId
     *
     * @return bool
     */
    public function deleteUser(int $id, int $accountId): bool
    {
        $user = User::where('id', $id)->where('account_id', $accountId)->first();
        if ($user) {
            return $user->delete(); // Soft delete (permanent - no restoration)
        }
        return false;
    }

    /**
     * Update user password
     *
     * @param int $id
     * @param int $accountId
     * @param string $password
     * @return User
     */
    public function updatePassword(int $id, int $accountId, string $password): User
    {
        $user = $this->findUserById($id, $accountId);
        $user->update([
            'password' => Hash::make($password),
        ]);
        return $user;
    }
}
