<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveRequest extends Model
{
    protected $fillable = ['employee_id','leave_type_id','start_date','end_date','total_days','yearly_days','countable_dates','day_fraction','half_day_slot','is_paid_snapshot','status','reason','decision_note','approved_by','approved_at','created_by'];

    protected function casts(): array
    {
        return ['start_date'=>'date','end_date'=>'date','total_days'=>'decimal:2','day_fraction'=>'decimal:2','is_paid_snapshot'=>'boolean','yearly_days'=>'array','countable_dates'=>'array','approved_at'=>'datetime'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
}
