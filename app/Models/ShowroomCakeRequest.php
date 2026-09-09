<?php

namespace App\Models;

use App\Enums\ShowroomCakeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShowroomCakeRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number', 'requesting_location_id', 'factory_location_id',
        'status', 'needed_by', 'notes', 'factory_notes',
        'created_by', 'handled_by', 'submitted_at', 'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'status'       => ShowroomCakeRequestStatus::class,
            'needed_by'    => 'date',
            'submitted_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function requestingLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'requesting_location_id');
    }

    public function factoryLocation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'factory_location_id');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function handledBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShowroomCakeRequestItem::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function canTransitionTo(ShowroomCakeRequestStatus $newStatus): bool
    {
        return in_array($newStatus, $this->status->allowedTransitions());
    }

    /** Generate a sequential request number, e.g. SCR-2026-00001 */
    public static function generateNumber(): string
{
    $year = now()->year;
    $prefix = "SCR-{$year}-";

    $latestNumber = static::withTrashed()
        ->where('request_number', 'like', "{$prefix}%")
        ->orderByRaw(
            'CAST(SUBSTRING(request_number, ?) AS UNSIGNED) DESC',
            [strlen($prefix) + 1]
        )
        ->value('request_number');

    $lastSequence = $latestNumber
        ? (int) substr($latestNumber, strlen($prefix))
        : 0;

    do {
        $lastSequence++;

        $requestNumber = $prefix
            . str_pad((string) $lastSequence, 5, '0', STR_PAD_LEFT);
    } while (
        static::withTrashed()
            ->where('request_number', $requestNumber)
            ->exists()
    );

    return $requestNumber;
}
}
