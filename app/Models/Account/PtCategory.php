<?php

namespace App\Models\Account;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PtCategory extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_pt_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'category_name',
    ];

    /**
     * Get the PT packages for this category.
     */
    public function ptPackages()
    {
        return $this->hasMany(PtPackage::class, 'category_id');
    }
}
