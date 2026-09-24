<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFaceProfile extends Model
{
    protected $fillable = [
        'employee_id',
        'provider',
        'provider_face_id_hash',
        'status',
        'enrolled_by',
        'enrolled_at',
        'activated_at',
        'last_verified_at',
        'revoked_at',
        'metadata',
    ];

    protected $hidden = [
        'provider_face_id_hash',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'activated_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'enrolled_by'
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->revoked_at === null;
    }
}
