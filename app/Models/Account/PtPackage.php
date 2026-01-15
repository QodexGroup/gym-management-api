<?php

namespace App\Models\Account;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PtPackage extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_pt_packages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'category_id',
        'package_name',
        'description',
        'number_of_sessions',
        'duration_per_session',
        'price',
        'features',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'number_of_sessions' => 'integer',
            'duration_per_session' => 'integer',
            'features' => 'array',
        ];
    }

    /**
     * Get the PT category for this package.
     */
    public function category()
    {
        return $this->belongsTo(PtCategory::class, 'category_id');
    }
}
