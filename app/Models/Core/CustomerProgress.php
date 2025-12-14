<?php

namespace App\Models\Core;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerProgress extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    protected $table = 'tb_customer_progress';

    protected $fillable = [
        'account_id',
        'customer_id',
        'recorded_by',
        // Basic Measurements
        'weight',
        'height',
        'body_fat_percentage',
        'bmi',
        // Body Measurements
        'chest',
        'waist',
        'hips',
        'left_arm',
        'right_arm',
        'left_thigh',
        'right_thigh',
        'left_calf',
        'right_calf',
        'skeletal_muscle_mass',
        'body_fat_mass',
        'total_body_water',
        'protein',
        'minerals',
        'visceral_fat_level',
        'basal_metabolic_rate',
        'data_source',
        'customer_scan_id',
        // Notes
        'notes',
        'recorded_date',
    ];

    protected function casts(): array
    {
        return [
            'recorded_date' => 'date',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'body_fat_percentage' => 'decimal:2',
            'bmi' => 'decimal:2',
            'chest' => 'decimal:2',
            'waist' => 'decimal:2',
            'hips' => 'decimal:2',
            'left_arm' => 'decimal:2',
            'right_arm' => 'decimal:2',
            'left_thigh' => 'decimal:2',
            'right_thigh' => 'decimal:2',
            'left_calf' => 'decimal:2',
            'right_calf' => 'decimal:2',
            'skeletal_muscle_mass' => 'decimal:2',
            'body_fat_mass' => 'decimal:2',
            'total_body_water' => 'decimal:2',
            'protein' => 'decimal:2',
            'minerals' => 'decimal:2',
            'visceral_fat_level' => 'decimal:2',
            'basal_metabolic_rate' => 'decimal:2',
        ];
    }

    /**
     * Get the customer that owns this progress record.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the files associated with this progress record (polymorphic).
     * This includes progress photos (remarks = 'progress_tracking').
     */
    public function files(): MorphMany
    {
        return $this->morphMany(CustomerFiles::class, 'fileable')
            ->where('account_id', 1); // Filter by account_id since all records use account_id = 1
    }

    /**
     * Get the progress photos (images) for this progress record.
     * Alias for files() filtered by remarks = 'progress_tracking'.
     */
    public function images(): MorphMany
    {
        return $this->morphMany(CustomerFiles::class, 'fileable')
            ->where('account_id', 1)
            ->where('remarks', 'progress_tracking');
    }

    /**
     * Get the associated scan for this progress record.
     */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(CustomerScans::class, 'customer_scan_id', 'id');
    }

}
