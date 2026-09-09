<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'user_id',
        'reply_to_id',
        'message',
        'message_type',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(
            ChatChannel::class,
            'channel_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'reply_to_id'
        );
    }

    public function replies(): HasMany
    {
        return $this->hasMany(
            self::class,
            'reply_to_id'
        );
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            ChatAttachment::class,
            'message_id'
        );
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(
            ChatMessageReceipt::class,
            'message_id'
        );
    }
}