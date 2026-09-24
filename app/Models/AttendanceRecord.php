<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id','work_shift_id','work_date','scheduled_start_at','scheduled_end_at',
        'check_in_at','check_out_at','status','worked_minutes','late_minutes',
        'early_leave_minutes','overtime_minutes','source','verification_method',
        'verification_provider','verification_reference','verification_location_id',
        'verification_metadata','notes','approved_by','approved_at','created_by'
    ];

    protected function casts(): array
    {
        return [
            'work_date'=>'date','scheduled_start_at'=>'datetime','scheduled_end_at'=>'datetime',
            'check_in_at'=>'datetime','check_out_at'=>'datetime','worked_minutes'=>'integer',
            'late_minutes'=>'integer','early_leave_minutes'=>'integer','overtime_minutes'=>'integer',
            'approved_at'=>'datetime',
            'verification_metadata'=>'array',
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function shift(): BelongsTo { return $this->belongsTo(WorkShift::class, 'work_shift_id'); }

    public function verificationLocation(): BelongsTo
    {
        return $this->belongsTo(
            Location::class,
            'verification_location_id'
        );
    }
}
