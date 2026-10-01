<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHrProfile extends Model
{
    protected $fillable = [
        'employee_id', 'emergency_name', 'emergency_phone', 'emergency_relationship',
        'contract_ends_on', 'onboarded_on', 'offboarded_on', 'lifecycle_notes',
    ];

    protected function casts(): array
    {
        return [
            'contract_ends_on' => 'date',
            'onboarded_on' => 'date',
            'offboarded_on' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
