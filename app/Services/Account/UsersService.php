<?php

namespace App\Services\Account;

use App\Models\User;
use App\Repositories\Account\UserPermissionRepository;
use App\Repositories\Account\UsersRepository;
use Illuminate\Support\Facades\DB;

class UsersService
{
    public function __construct(
        private UsersRepository $usersRepository,
        private UserPermissionRepository $permissionRepository
    ) {
    }

    /**
     * Create a new user with permissions
     *
     * @param array $data
     * @return User
     * @throws \Throwable
     */
    public function createUser(array $data): User
    {
        try {
            return DB::transaction(function () use ($data) {
                // Extract permissions from data
                $permissions = $data['permissions'] ?? [];
                unset($data['permissions']);

                // Create user via repository
                $user = $this->usersRepository->createUser($data);

                // Attach permissions
                $this->attachPermissions($user->id, $permissions);

                return $user->load('permissions')->fresh();
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Update user and permissions
     *
     * @param int $id
     * @param array $data
     * @return User
     * @throws \Throwable
     */
    public function updateUser(int $id, array $data): User
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                // Extract permissions from data
                $permissions = $data['permissions'] ?? null;
                unset($data['permissions']);

                // Update user via repository
                $user = $this->usersRepository->updateUser($id, $data);

                // Update permissions if provided
                if ($permissions !== null) {
                    $this->syncPermissions($user->id, $permissions);
                }

                return $user->load('permissions')->fresh();
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Attach permissions to a user
     *
     * @param int $userId
     * @param array $permissions
     * @return void
     * @throws \Throwable
     */
    private function attachPermissions(int $userId, array $permissions): void
    {
        if (empty($permissions)) {
            return;
        }

        try {
            foreach ($permissions as $permission) {
                $this->permissionRepository->createPermission($userId, $permission);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Sync permissions for a user (delete existing and create new)
     *
     * @param int $userId
     * @param array $permissions
     * @return void
     * @throws \Throwable
     */
    private function syncPermissions(int $userId, array $permissions): void
    {
        try {
            // Delete existing permissions
            $this->permissionRepository->deleteUserPermissions($userId);

            // Create new permissions
            if (!empty($permissions)) {
                $this->attachPermissions($userId, $permissions);
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
