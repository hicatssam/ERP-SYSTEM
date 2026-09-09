<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBiometricMapping extends Model
{
    protected $fillable = ['attendance_device_id','employee_id','device_user_id','is_active','notes'];

    protected function casts(): array { return ['is_active'=>'boolean']; }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
