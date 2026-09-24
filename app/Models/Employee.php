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

    public function faceProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeFaceProfile::class);
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
            $user->can('employees.view_all')
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
        );
    }

    public function primaryLocation(): ?Location
    {
        return $this->locations()
            ->wherePivot('is_primary', true)
            ->first();
    }

    public function isActive(): bool
    {
        return $this->employment_status === EmploymentStatus::Active;
    }
}