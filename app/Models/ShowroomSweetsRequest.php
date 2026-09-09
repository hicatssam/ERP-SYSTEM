<?php

namespace App\Models;

use App\Enums\ShowroomSweetsRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShowroomSweetsRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_number',
        'requesting_location_id',
        'factory_location_id',
        'status',
        'needed_by',
        'notes',
        'factory_notes',

        'created_by',
        'handled_by',
        'dispatched_by',
        'received_by',

        'submitted_at',
        'dispatched_at',
        'received_at',
        'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShowroomSweetsRequestStatus::class,

            'needed_by' => 'date',

            'submitted_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | الفرع الطالب
    |--------------------------------------------------------------------------
    */

    public function requestingLocation(): BelongsTo
    {
        return $this->belongsTo(
            Location::class,
            'requesting_location_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | المصنع
    |--------------------------------------------------------------------------
    */

    public function factoryLocation(): BelongsTo
    {
        return $this->belongsTo(
            Location::class,
            'factory_location_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | منشئ الطلب
    |--------------------------------------------------------------------------
    */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | من بدأ التجهيز بالمصنع
    |--------------------------------------------------------------------------
    */

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'handled_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | من أرسل الطلب مع التوصيل
    |--------------------------------------------------------------------------
    */

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'dispatched_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | من استلم الطلب في الفرع
    |--------------------------------------------------------------------------
    */

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'received_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | الأصناف
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(
            ShowroomSweetsRequestItem::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | هل الانتقال مسموح؟
    |--------------------------------------------------------------------------
    */

    public function canTransitionTo(
        ShowroomSweetsRequestStatus $status
    ): bool {
        return in_array(
            $status,
            $this->status->allowedTransitions(),
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | توليد رقم الطلب
    |--------------------------------------------------------------------------
    */

    public static function generateNumber(): string
    {
        $year = now()->year;

        $prefix = "SSR-{$year}-";

        $latestNumber = static::withTrashed()
            ->where(
                'request_number',
                'like',
                "{$prefix}%"
            )
            ->orderByRaw(
                'CAST(SUBSTRING(request_number, ?) AS UNSIGNED) DESC',
                [
                    strlen($prefix) + 1,
                ]
            )
            ->value('request_number');

        $sequence = $latestNumber
            ? (int) substr(
                $latestNumber,
                strlen($prefix)
            )
            : 0;

        do {
            $sequence++;

            $requestNumber =
                $prefix .
                str_pad(
                    (string) $sequence,
                    5,
                    '0',
                    STR_PAD_LEFT
                );

        } while (
            static::withTrashed()
                ->where(
                    'request_number',
                    $requestNumber
                )
                ->exists()
        );

        return $requestNumber;
    }
}