<?php

namespace App\Services\Account;

use App\Helpers\GenericData;
use App\Models\User;
use App\Repositories\Account\UserPermissionRepository;
use App\Repositories\Account\UsersRepository;
use App\Services\Account\AccountLimitService;
use App\Services\Auth\FirebaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Exception\AuthException;

class UsersService
{
    public function __construct(
        private UsersRepository $usersRepository,
        private UserPermissionRepository $permissionRepository,
        private AccountLimitService $accountLimitService
    ) {
    }

    /**
     * Create a new user with permissions
     *
     * @param GenericData $genericData
     * @return User
     * @throws \Throwable
     */
    public function createUser(GenericData $genericData): User
    {
        $check = $this->accountLimitService->canCreate($genericData->userData->account_id, AccountLimitService::RESOURCE_USERS);
        if (!$check['allowed']) {
            throw new \Exception($check['message'] ?? 'Limit reached');
        }

        try {
            return DB::transaction(function () use ($genericData) {
                $accountId = $genericData->userData->account_id;

                // Extract permissions from data
                $permissions = $genericData->getData()->permissions ?? [];
                unset($genericData->getData()->permissions);
                $genericData->syncDataArray();

                // Create Firebase user automatically using Admin SDK
                if (empty($genericData->getData()->firebaseUid) && !empty($genericData->getData()->email)) {
                    $this->ensureFirebaseUser($genericData);
                } elseif (empty($genericData->getData()->firebaseUid)) {
                    throw new \InvalidArgumentException('Email is required to create a Firebase user.');
                }

                // Ensure firebaseUid is mapped to firebase_uid
                $genericData->getData()->firebase_uid = $genericData->getData()->firebaseUid;
                unset($genericData->getData()->firebaseUid);
                $genericData->syncDataArray();

                // Check for existing user by Firebase UID first (since ensureFirebaseUser already handled Firebase)
                $existingUserByFirebaseUid = $this->usersRepository->findUserByFirebaseUid(
                    $genericData->getData()->firebase_uid,
                    $accountId
                );

                if ($existingUserByFirebaseUid) {
                    // If soft-deleted, throw error (no restoration allowed)
                    if ($existingUserByFirebaseUid->trashed()) {
                        throw new \InvalidArgumentException('A user with this Firebase UID was previously deleted and cannot be recreated.');
                    } else {
                        throw new \InvalidArgumentException('A user with this Firebase UID already exists.');
                    }
                }


                // Ensure account_id is set in data
                $genericData->getData()->account_id = $accountId;
                $genericData->syncDataArray();

                // Create user via repository
                $user = $this->usersRepository->createUser($genericData);

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
     * @param GenericData $genericData
     * @return User
     * @throws \Throwable
     */
    public function updateUser(int $id, GenericData $genericData): User
    {
        try {
            return DB::transaction(function () use ($id, $genericData) {

                // Extract permissions from data
                $permissions = $genericData->getData()->permissions ?? null;
                unset($genericData->getData()->permissions);
                $genericData->syncDataArray();

                // Ensure firebaseUid is mapped to firebase_uid if provided
                if (isset($genericData->getData()->firebaseUid)) {
                    $genericData->getData()->firebase_uid = $genericData->getData()->firebaseUid;
                    unset($genericData->getData()->firebaseUid);
                    $genericData->syncDataArray();
                }

                // Update user via repository
                $user = $this->usersRepository->updateUser($id, $genericData);

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
     * Ensure Firebase user exists and is enabled
     *
     * @param GenericData $genericData
     * @return void
     * @throws \InvalidArgumentException
     */
    private function ensureFirebaseUser(GenericData $genericData): void
    {
        try {
            $existingFirebaseUser = FirebaseService::auth()->getUserByEmail($genericData->getData()->email);
            $genericData->getData()->firebaseUid = $existingFirebaseUser->uid;
            $genericData->syncDataArray();

            // Re-enable if disabled
            if ($existingFirebaseUser->disabled ?? false) {
                $this->enableFirebaseUser($existingFirebaseUser->uid);
            }
        } catch (UserNotFound $e) {
            // Create new Firebase user
            $firebaseUser = FirebaseService::auth()->createUser([
                'email' => $genericData->getData()->email,
                'password' => $genericData->getData()->password,
                'displayName' => trim(($genericData->getData()->firstname ?? '') . ' ' . ($genericData->getData()->lastname ?? '')),
                'emailVerified' => false,
            ]);
            $genericData->getData()->firebaseUid = $firebaseUser->uid;
            $genericData->syncDataArray();
            Log::info('Created new Firebase user for email: ' . $genericData->getData()->email);
        } catch (AuthException $e) {
            Log::error('Failed to create/get Firebase user: ' . $e->getMessage());
            throw new \InvalidArgumentException('Failed to create or retrieve Firebase user: ' . $e->getMessage());
        }
    }

    /**
     * Enable a Firebase user
     *
     * @param string $firebaseUid
     * @return void
     */
    private function enableFirebaseUser(string $firebaseUid): void
    {
        try {
            FirebaseService::auth()->enableUser($firebaseUid);
            Log::info('Enabled Firebase user: ' . $firebaseUid);
        } catch (\Exception $e) {
            Log::warning('Failed to enable Firebase user: ' . $e->getMessage());
        }
    }

    /**
     * Disable a Firebase user
     *
     * @param string $firebaseUid
     * @return void
     */
    private function disableFirebaseUser(string $firebaseUid): void
    {
        try {
            FirebaseService::auth()->disableUser($firebaseUid);
            Log::info('Disabled Firebase user: ' . $firebaseUid);
        } catch (\Exception $e) {
            Log::warning('Failed to disable Firebase user: ' . $e->getMessage());
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

    /**
     * Delete a user (soft delete) and disable Firebase user
     * Note: Once deleted, users cannot be restored
     *
     * @param int $id
     * @param int $accountId
     * @return bool
     * @throws \Throwable
     */
    public function deleteUser(int $id, int $accountId): bool
    {
        try {
            return DB::transaction(function () use ($id, $accountId) {
                $user = $this->usersRepository->findUserById($id, $accountId);

                // Disable Firebase user
                if ($user->firebase_uid) {
                    $this->disableFirebaseUser($user->firebase_uid);
                }

                // Soft delete the user (permanent - no restoration)
                return $user->delete();
            });
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Reset user password in Firebase
     *
     * @param int $id
     * @param GenericData $genericData
     * @return User
     * @throws \Throwable
     */
    public function resetPassword(int $id, GenericData $genericData): User
    {
        try {
            return DB::transaction(function () use ($id, $genericData) {
                $accountId = $genericData->userData->account_id;
                $password = $genericData->getData()->password;

                $user = $this->usersRepository->findUserById($id, $accountId);

                if (!$user->firebase_uid) {
                    throw new \InvalidArgumentException('User does not have a Firebase UID.');
                }

                // Update Firebase user password
                FirebaseService::auth()->updateUser($user->firebase_uid, [
                    'password' => $password,
                ]);

                // Update password in database (hashed)
                $user = $this->usersRepository->updatePassword($id, $accountId, $password);

                return $user->load('permissions')->fresh();
            });
        } catch (\Throwable $th) {
            Log::error('Failed to reset password: ' . $th->getMessage());
            throw $th;
        }
    }
}
