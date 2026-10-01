<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveCarryover extends Model
{
    protected $fillable = [
        'employee_id', 'leave_type_id', 'year', 'days', 'granted_by', 'note',
    ];

    protected function casts(): array
    {
        return ['days' => 'decimal:2', 'year' => 'integer'];
    }
}
