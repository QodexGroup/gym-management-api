<?php

namespace App\Models\Core;

use App\Models\Account\ClassScheduleSession;
use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSessionBooking extends Model
{
    use HasFactory, HasCamelCaseAttributes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_class_session_bookings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'class_schedule_session_id',
        'customer_id',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * Get the class schedule session for this booking.
     */
    public function classScheduleSession(): BelongsTo
    {
        return $this->belongsTo(ClassScheduleSession::class, 'class_schedule_session_id');
    }

    /**
     * Get the customer for this booking.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the user who created the booking.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the booking.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
