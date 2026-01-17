<?php

namespace App\Models\Account;

use App\Models\User;
use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassSchedule extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_class_schedule';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'class_name',
        'description',
        'coach_id',
        'class_type',
        'capacity',
        'duration',
        'start_date',
        'schedule_type',
        'recurring_interval',
        'number_of_sessions',
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
            'start_date' => 'datetime',
            'schedule_type' => 'integer',
            'capacity' => 'integer',
            'duration' => 'integer',
            'number_of_sessions' => 'integer',
        ];
    }

    /**
     * Get the coach for this class schedule.
     */
    public function coach()
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    /**
     * Get the sessions for this class schedule.
     */
    public function sessions()
    {
        return $this->hasMany(ClassScheduleSession::class, 'class_schedule_id');
    }

    /**
     * Get the user who created the schedule.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who updated the schedule.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
