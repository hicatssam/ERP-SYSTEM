<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkShift extends Model
{
    protected $fillable = ['code','name','location_id','start_time','end_time','break_minutes','grace_minutes','overtime_after_minutes','work_days','is_active','notes','created_by'];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'is_active' => 'boolean',
            'break_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'overtime_after_minutes' => 'integer',
        ];
    }

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function assignments(): HasMany { return $this->hasMany(EmployeeShiftAssignment::class); }
    public function records(): HasMany { return $this->hasMany(AttendanceRecord::class); }
}
