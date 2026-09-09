<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveRequest extends Model
{
    protected $fillable = ['employee_id','leave_type_id','start_date','end_date','total_days','status','reason','decision_note','approved_by','approved_at','created_by'];

    protected function casts(): array
    {
        return ['start_date'=>'date','end_date'=>'date','total_days'=>'decimal:2','approved_at'=>'datetime'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function leaveType(): BelongsTo { return $this->belongsTo(LeaveType::class); }
}
