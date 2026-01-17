<?php

namespace App\Models\Account;

use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
