<?php

namespace App\Modules\Imports\Models;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use App\Modules\Imports\Constants\ImportConstant;

class ImportJob extends Model
{
    use HasCamelCaseAttributes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_import_jobs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'import_type',
        'original_filename',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'success_count',
        'failure_count',
        'skipped_count',
        'column_mapping',
        'file_headers',
        'options',
        'result_file_path',
        'error_message',
        'started_at',
        'completed_at',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'file_headers' => 'array',
            'options' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'success_count' => 'integer',
            'failure_count' => 'integer',
            'skipped_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the per-row results for this import job.
     */
    public function results()
    {
        return $this->hasMany(ImportResult::class, 'import_job_id');
    }

    /**
     * Progress as a whole-number percentage (0-100).
     *
     * @return int
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_rows <= 0) {
            return $this->status === ImportConstant::STATUS_COMPLETED ? 100 : 0;
        }
        return (int) min(100, floor(($this->processed_rows / $this->total_rows) * 100));
    }
}
