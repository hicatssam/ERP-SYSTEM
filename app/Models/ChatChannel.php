<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'name',
        'type',
        'direct_key',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(
            Location::class
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            ChatMessage::class,
            'channel_id'
        );
    }

    public function reads(): HasMany
    {
        return $this->hasMany(
            ChatRead::class,
            'channel_id'
        );
    }

    public function members(): HasMany
    {
        return $this->hasMany(
            ChatChannelMember::class,
            'channel_id'
        );
    }

    public function isBranchChannel(): bool
    {
        return $this->type === 'branch';
    }

    public function isDirectChannel(): bool
    {
        return $this->type === 'direct';
    }
}