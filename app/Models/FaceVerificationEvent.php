<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceVerificationEvent extends Model
{
    protected $fillable = [
        'provider',
        'event_name',
        'provider_face_id_hash',
        'app_id',
        'client_ip',
        'fingerprint',
        'occurred_at',
        'received_at',
        'consumed_at',
        'payload',
        'metadata',
    ];

    protected $hidden = [
        'provider_face_id_hash',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'received_at' => 'datetime',
            'consumed_at' => 'datetime',
            'payload' => 'array',
            'metadata' => 'array',
        ];
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
