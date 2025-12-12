<?php

namespace App\Models\Core;

use App\Traits\HasCamelCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, HasCamelCaseAttributes, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tb_customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'photo',
        'phone_number',
        'email',
        'address',
        'medical_notes',
        'emergency_contact_name',
        'emergency_contact_phone',
        'blood_type',
        'allergies',
        'current_medications',
        'medical_conditions',
        'doctor_name',
        'doctor_phone',
        'insurance_provider',
        'insurance_policy_number',
        'emergency_contact_relationship',
        'emergency_contact_address',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }
}

