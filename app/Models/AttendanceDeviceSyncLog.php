<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDeviceSyncLog extends Model
{
    protected $fillable = [
        'attendance_device_id','direction','status','received_count','processed_count',
        'failed_count','started_at','finished_at','message','metadata',
    ];

    protected function casts(): array
    {
        return ['started_at'=>'datetime','finished_at'=>'datetime','metadata'=>'array'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
