<?php

namespace App\Models\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generic per-account key/value setting (EAV). Natural key is (account_id, set_key);
 * there is no surrogate id, so this model is used for bulk reads and static
 * upserts only (never single-key find/save).
 */
class AccountSystemSetting extends Model
{
    protected $table = 'account_system_settings';

    public $incrementing = false;

    protected $primaryKey = 'set_key';

    protected $keyType = 'string';

    protected $fillable = [
        'account_id',
        'set_key',
        'set_value',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
