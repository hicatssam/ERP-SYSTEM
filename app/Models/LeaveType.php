<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = ['code','name','is_paid','annual_days','is_active','notes'];

    protected function casts(): array
    {
        return ['is_paid'=>'boolean','annual_days'=>'decimal:2','is_active'=>'boolean'];
    }

    public function requests(): HasMany { return $this->hasMany(EmployeeLeaveRequest::class); }
}
