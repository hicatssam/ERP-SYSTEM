<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShiftAssignment extends Model
{
    protected $fillable = ['employee_id','work_shift_id','effective_from','effective_to','is_primary','notes','created_by'];

    protected function casts(): array
    {
        return ['effective_from'=>'date','effective_to'=>'date','is_primary'=>'boolean'];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function shift(): BelongsTo { return $this->belongsTo(WorkShift::class, 'work_shift_id'); }
}
