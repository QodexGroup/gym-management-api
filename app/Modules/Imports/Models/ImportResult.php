<?php

namespace App\Modules\Imports\Models;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Model;

class ImportResult extends Model
{
    use HasCamelCaseAttributes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_import_results';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'import_job_id',
        'account_id',
        'row_number',
        'status',
        'original_data',
        'errors',
        'created_record_id',
        'message',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'original_data' => 'array',
            'errors' => 'array',
            'row_number' => 'integer',
        ];
    }

    /**
     * Get the import job this result belongs to.
     */
    public function importJob()
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
