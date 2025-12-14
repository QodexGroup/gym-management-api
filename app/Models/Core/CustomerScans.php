<?php

namespace App\Models\Core;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerScans extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    protected $table = 'tb_customer_scans';

    protected $fillable = [
        'account_id',
        'customer_id',
        'uploaded_by',
        'scan_type',
        'scan_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scan_date' => 'date',
        ];
    }

    /**
     * Get the customer that owns this scan.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the files associated with this scan (polymorphic).
     */
    public function files(): MorphMany
    {
        return $this->morphMany(CustomerFiles::class, 'fileable');
    }
}
