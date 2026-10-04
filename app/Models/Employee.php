<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number',
        'full_name',
        'profile_image',
        'phone',
        'email',
        'job_title',
        'hire_date',
        'employment_status',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'employment_status' => EmploymentStatus::class,
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(User::class);
    }

    public function faceProfiles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(
            EmployeeFaceProfile::class
        );
    }

    public function faceProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this
            ->hasOne(
                EmployeeFaceProfile::class
            )
            ->where(
                'provider',
                (string) config(
                    'attendance-face.provider',
                    'compreface'
                )
            );
    }

    public function locations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'employee_locations')
            ->withPivot('is_primary', 'started_at', 'ended_at')
            ->withTimestamps();
    }

    public function employeeLocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeLocation::class);
    }

    public function attendanceRecords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function selfAttendanceRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeSelfAttendanceRequest::class);
    }

    public function hrProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeHrProfile::class);
    }

    public function orgAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeOrgAssignment::class)->orderByDesc('effective_from');
    }

    public function currentOrgAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeOrgAssignment::class)
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', now()->toDateString()))
            ->orderByDesc('effective_from');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function cashSessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * Admin أو من لديه employees.view_all يرى الجميع.
     * باقي المستخدمين يرون فقط موظفي موقعهم الرئيسي.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if (
            $user->isAdmin() ||
            ($user->can('employees.view_all') && ! $user->hasRole('Branch Manager'))
        ) {
            return $query;
        }

        $locationId = $user->primaryLocation()?->id;

        if (! $locationId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'employeeLocations',
            fn (Builder $employeeLocations) => $employeeLocations
                ->where(
                    'employee_locations.location_id',
                    $locationId
                )
                ->where(
                    'employee_locations.is_primary',
                    true
                )
                ->where(fn (Builder $dates) => $dates
                    ->whereNull('employee_locations.started_at')
                    ->orWhereDate('employee_locations.started_at', '<=', now()->toDateString()))
                ->where(fn (Builder $dates) => $dates
                    ->whereNull('employee_locations.ended_at')
                    ->orWhereDate('employee_locations.ended_at', '>=', now()->toDateString()))
        );
    }

    public function primaryLocation(): ?Location
    {
        return $this->locations()
            ->wherePivot('is_primary', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('employee_locations.started_at')
                ->orWhereDate('employee_locations.started_at', '<=', now()->toDateString()))
            ->where(fn (Builder $query) => $query
                ->whereNull('employee_locations.ended_at')
                ->orWhereDate('employee_locations.ended_at', '>=', now()->toDateString()))
            ->orderByPivot('started_at', 'desc')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->employment_status === EmploymentStatus::Active;
    }
}
