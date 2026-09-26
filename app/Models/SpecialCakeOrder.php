<?php

namespace App\Models;

use App\Enums\CakeOrderStatus;
use App\Enums\ElectronicPaymentMethod;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentArrangement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecialCakeOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number', 'customer_id', 'origin_branch_id', 'factory_location_id',
        'required_date', 'required_time', 'cake_type', 'cake_size', 'cake_weight',
        'persons_count', 'flavor', 'filling', 'shape', 'color', 'cake_text',
        'theme', 'special_instructions', 'image_cover_type', 'total_price',
        'discount_type', 'discount_value', 'discount_amount', 'net_price',
        'status', 'payment_status', 'payment_arrangement', 'payment_channel',
        'created_by', 'assigned_to',
        'scheduled_at', 'completed_at', 'cancelled_at', 'cancellation_reason', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'required_date'       => 'date',
            'total_price'         => 'decimal:2',
            'discount_value'      => 'decimal:2',
            'discount_amount'     => 'decimal:2',
            'net_price'           => 'decimal:2',
            'scheduled_at'        => 'datetime',
            'completed_at'        => 'datetime',
            'cancelled_at'        => 'datetime',
            'status'              => CakeOrderStatus::class,
            'payment_status'      => OrderPaymentStatus::class,
            'payment_arrangement' => PaymentArrangement::class,
            'payment_channel'     => ElectronicPaymentMethod::class,
        ];
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function originBranch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'origin_branch_id');
    }

    public function factory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class, 'factory_location_id');
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CakeOrderAttachment::class);
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CakeOrderComment::class)->latest();
    }

    public function statusHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CakeOrderStatusHistory::class)->latest();
    }

    public function receivingIssues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CakeReceivingIssue::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->where('order_type', 'special_cake_order');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Invoice::class, 'order_id')->where('order_type', 'special_cake_order');
    }

    public function isInTerminalState(): bool
    {
        if ($this->status instanceof CakeOrderStatus) {
            return $this->status->isTerminal();
        }

        return in_array(
            (string) $this->status,
            ['completed', 'cancelled', 'canceled', 'rejected', 'delivered'],
            true
        );
    }

    public static function allowedTransitions(): array
    {
        return [
            'draft' => ['pending', 'cancelled'],
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['ready', 'cancelled'],
            'ready' => ['completed', 'cancelled'],
        ];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $current = $this->status instanceof CakeOrderStatus
            ? $this->status->workflowValue()
            : self::normalizeLegacyWorkflowStatus(
                (string) $this->status
            );

        return in_array(
            $newStatus,
            self::allowedTransitions()[$current] ?? [],
            true
        );
    }

    public static function normalizeLegacyWorkflowStatus(
        string $status
    ): string {
        $enum = CakeOrderStatus::tryFrom($status);

        if ($enum) {
            return $enum->workflowValue();
        }

        return match ($status) {
            'pending_deposit',
            'deposit_paid' => 'pending',

            'in_decoration' => 'in_progress',

            'dispatched_to_branch',
            'received_at_branch',
            'ready_for_pickup' => 'ready',

            'delivered' => 'completed',

            'canceled' => 'cancelled',

            default => $status,
        };
    }
}
