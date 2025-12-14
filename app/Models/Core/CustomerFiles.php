<?php

namespace App\Models\Core;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerFiles extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    protected $table = 'tb_customer_files';

    protected $fillable = [
        'account_id',
        'customer_id',
        'fileable_type',
        'fileable_id',
        'remarks',
        'file_name',
        'file_url',
        'file_size',
        'mime_type',
        'file_date',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_date' => 'date',
            'file_size' => 'decimal:2',
        ];
    }

    /**
     * Get the customer that owns this file.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the parent fileable model (CustomerProgress or CustomerScans).
     */
    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }
}
