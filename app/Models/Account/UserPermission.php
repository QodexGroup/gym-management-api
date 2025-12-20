<?php

namespace App\Models\Account;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class UserPermission extends Model
{
    use HasFactory;

    protected $table = 'tb_user_permissions';

    protected $fillable = [
        'user_id',
        'permission',
    ];

    /**
     * Get the user that owns the permission.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

