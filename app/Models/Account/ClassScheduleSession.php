<?php

namespace App\Models\Account;

use App\Models\User;
use App\Models\Account\ClassSchedule;
use App\Models\Core\ClassSessionBooking;
use App\Models\Core\PtBooking;
use App\Constant\ClassSessionBookingStatusConstant;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ClassScheduleSession extends Model
{
    use HasFactory, HasCamelCaseAttributes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_class_schedule_sessions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'class_schedule_id',
        'start_time',
        'end_time',
        'attendance_count',
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
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'attendance_count' => 'integer',
        ];
    }

    /**
     * Get the class schedule for this session.
     */
    public function classSchedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'class_schedule_id');
    }

    /**
     * Get the user who created the session.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the session.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all bookings for this session.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(ClassSessionBooking::class, 'class_schedule_session_id');
    }

    /**
     * Get PT bookings for this session through class schedule.
     */
    public function ptBookings(): HasManyThrough
    {
        return $this->hasManyThrough(
            PtBooking::class,
            ClassSchedule::class,
            'id', // Foreign key on ClassSchedule table
            'class_schedule_id', // Foreign key on PtBooking table
            'class_schedule_id', // Local key on ClassScheduleSession table
            'id' // Local key on ClassSchedule table
        );
    }

    /**
     * Get the dynamic attendance count (excluding cancelled bookings).
     * This accessor calculates the count from actual bookings.
     * The repository uses withCount() to optimize this, which sets the attribute.
     */
    public function getAttendanceCountAttribute(): int
    {
        if (isset($this->attributes['attendance_count'])) {
            return (int) $this->attributes['attendance_count'];
        }

        // Fallback: If the relationship is already loaded, use it
        if ($this->relationLoaded('bookings')) {
            return $this->bookings
                ->where('status', '!=', ClassSessionBookingStatusConstant::STATUS_CANCELLED)
                ->count();
        }

        // Final fallback: query the database
        return $this->bookings()
            ->where('status', '!=', ClassSessionBookingStatusConstant::STATUS_CANCELLED)
            ->count();
    }
}
