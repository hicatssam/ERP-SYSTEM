<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'employee_id',
        'username',
        'email',
        'password',
        'is_active',
        'profile_image',
        'must_change_password',
        'last_login_at',
        'failed_login_attempts',
        'login_locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' =>
                'boolean',

            'must_change_password' =>
                'boolean',

            'last_login_at' =>
                'datetime',

            'login_locked_until' =>
                'datetime',
        ];
    }

    // Relationships

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(
            Employee::class
        );
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(
            ActivityLog::class
        );
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(
            ChatMessage::class
        );
    }

    public function chatReads(): HasMany
    {
        return $this->hasMany(
            ChatRead::class
        );
    }

    // Helpers

    public function isLocked(): bool
    {
        return $this->login_locked_until
            && $this
                ->login_locked_until
                ->isFuture();
    }

    public function primaryLocation(): ?Location
    {
        return $this
            ->employee
            ?->primaryLocation();
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole([
            'Admin',
            'super-admin',
        ]);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->employee?->full_name
            ?: 'مستخدم رقم '.$this->getKey();
    }

    public function displayName(): string
    {
        return $this->display_name;
    }
}
