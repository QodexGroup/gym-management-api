<?php

namespace App\Repositories\Account;

use App\Models\Account\UserPermission;
use Illuminate\Support\Collection;

class UserPermissionRepository
{
    /**
     * Get all permissions for a user
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserPermissions(int $userId): Collection
    {
        return UserPermission::where('user_id', $userId)->get();
    }

    /**
     * Create a permission for a user
     *
     * @param int $userId
     * @param string $permission
     * @return UserPermission
     */
    public function createPermission(int $userId, string $permission): UserPermission
    {
        return UserPermission::create([
            'user_id' => $userId,
            'permission' => $permission,
        ]);
    }


    /**
     * Delete all permissions for a user
     *
     * @param int $userId
     * @return bool
     */
    public function deleteUserPermissions(int $userId): bool
    {
        return UserPermission::where('user_id', $userId)->delete();
    }
}

