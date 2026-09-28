<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AssistantUserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'topic_mode',
        'allowed_intents',
        'allow_action_suggestions',
        'max_items',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allowed_intents' => 'array',
            'allow_action_suggestions' => 'boolean',
            'max_items' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
