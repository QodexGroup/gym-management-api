<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Account;
use App\Models\Account\UserPermission;
use App\Models\Core\Customer;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'firstname',
        'lastname',
        'email',
        'password',
        'firebase_uid',
        'role',
        'phone',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->firstname} {$this->lastname}");
    }

    /**
     * Get the customers assigned to this trainer.
     */
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'tb_customer_trainor', 'trainer_id', 'customer_id')
            ->withTimestamps();
    }

    /**
     * Get the permissions for the user.
     */
    public function permissions()
    {
        return $this->hasMany(UserPermission::class, 'user_id');
    }

    /**
     * Get the account (gym/organization) this user belongs to.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the permission names as an array.
     * Returns empty array if user has no permissions (e.g., admin with full access).
     *
     * @return array
     */
    public function getPermissionNamesAttribute(): array
    {
        try {
            // Ensure permissions are loaded
            if (!$this->relationLoaded('permissions')) {
                $this->load('permissions');
            }

            // If permissions collection is empty or null, return empty array
            if (!$this->permissions || $this->permissions->isEmpty()) {
                return [];
            }

            return $this->permissions->pluck('permission')->toArray();
        } catch (\Exception $e) {
            // Return empty array if there's any error
            return [];
        }
    }
}
