<?php

namespace App\Models\Common;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Walkin extends Model
{
    use HasFactory, SoftDeletes, HasCamelCaseAttributes;

    protected $table = 'tb_walkin';
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * @return HasMany
     */
    public function walkinCustomers(): HasMany
    {
        return $this->hasMany(WalkinCustomer::class, 'walkin_id', 'id');
    }



}
