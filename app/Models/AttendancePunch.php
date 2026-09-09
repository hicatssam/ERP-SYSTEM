<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    protected $fillable = [
        'attendance_device_id','employee_biometric_mapping_id','employee_id',
        'device_user_id','external_id','punch_at','punch_type','work_date',
        'status','fingerprint','raw_payload','processed_at','error_message',
    ];

    protected function casts(): array
    {
        return [
            'punch_at'=>'datetime','work_date'=>'date','raw_payload'=>'array','processed_at'=>'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(EmployeeBiometricMapping::class, 'employee_biometric_mapping_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
