<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetupWizardRun extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'status',
        'current_step',
        'business_profile_id',
        'business_profile_code',
        'business_data',
        'branding_data',
        'operational_data',
        'module_plan',
        'temporary_files',
        'created_by',
        'updated_by',
        'completed_by',
        'last_saved_at',
        'completed_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'current_step' => 'integer',
            'business_data' => 'array',
            'branding_data' => 'array',
            'operational_data' => 'array',
            'module_plan' => 'array',
            'temporary_files' => 'array',
            'last_saved_at' => 'datetime',
            'completed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function businessProfile(): BelongsTo
    {
        return $this->belongsTo(BusinessProfile::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
