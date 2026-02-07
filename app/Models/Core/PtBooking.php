<?php

namespace App\Models\Core;

use App\Models\Account\ClassSchedule;
use App\Models\Account\PtPackage;
use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtBooking extends Model
{
    use HasFactory, HasCamelCaseAttributes;

    protected $table = 'tb_customer_pt_bookings';


    protected $fillable = [
        'account_id',
        'customer_id',
        'pt_package_id',
        'coach_id',
        'class_schedule_id',
        'booking_date',
        'booking_time',
        'duration',
        'booking_notes',
        'status',
        'created_by',
        'updated_by'
    ];

    /**
     * @return BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo
     */
    public function ptPackage(): BelongsTo
    {
        return $this->belongsTo(PtPackage::class);
    }

    /**
     * @return BelongsTo
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo
     */
    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'class_schedule_id');
    }
}
