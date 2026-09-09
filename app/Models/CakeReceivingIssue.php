<?php

namespace App\Models;

use App\Enums\CakeReceivingIssueType;
use App\Enums\DiscrepancyStatus;
use Illuminate\Database\Eloquent\Model;

class CakeReceivingIssue extends Model
{
    protected $fillable = [
        'special_cake_order_id', 'issue_type', 'description',
        'image', 'status', 'reported_by', 'resolved_by', 'resolved_at', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'issue_type'  => CakeReceivingIssueType::class,
            'status'      => DiscrepancyStatus::class,
        ];
    }

    public function specialCakeOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SpecialCakeOrder::class);
    }

    public function reportedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
