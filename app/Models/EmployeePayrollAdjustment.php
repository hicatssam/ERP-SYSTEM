<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollAdjustment extends Model
{
    protected $fillable = [
        'employee_id','payroll_period_id','kind','name','amount','is_recurring',
        'effective_from','effective_to','status','notes','source_type','source_id','metadata','created_by'
    ];

    protected function casts(): array
    {
        return [
            'amount'=>'decimal:4','is_recurring'=>'boolean','effective_from'=>'date',
            'effective_to'=>'date','metadata'=>'array'
        ];
    }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function period(): BelongsTo { return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id'); }
}
