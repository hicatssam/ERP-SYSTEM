<?php
namespace App\Models;
use Carbon\Carbon;
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

    public function daysForYear(int $year): float
    {
        if ($this->yearly_days !== null) {
            return (float) ($this->yearly_days[$year] ?? 0);
        }

        // Legacy requests have no yearly breakdown. Split their actual date
        // span instead of charging the entire request to each matching year.
        $from = $this->start_date->copy()->max(Carbon::create($year, 1, 1));
        $to = $this->end_date->copy()->min(Carbon::create($year, 12, 31));

        return $to->lt($from) ? 0.0 : ((int) $from->diffInDays($to) + 1)
            * (float) ($this->day_fraction ?? 1);
    }
}
