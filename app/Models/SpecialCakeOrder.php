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
        return $this->status?->isTerminal() ?? in_array($this->status, ['completed', 'cancelled', 'rejected']);
    }

    public static function allowedTransitions(): array
    {
        return [
            'draft'                  => ['pending_factory_review', 'cancelled'],
            'pending_factory_review' => ['accepted', 'rejected', 'modification_requested', 'cancelled'],
            'modification_requested' => ['pending_factory_review', 'cancelled'],
            'accepted'               => ['scheduled', 'cancelled'],
            'scheduled'              => ['in_preparation', 'delayed', 'cancelled'],
            'in_preparation'         => ['decorating', 'delayed'],
            'decorating'             => ['quality_check'],
            'quality_check'          => ['ready', 'decorating', 'in_preparation'],
            'ready'                  => ['sent_to_branch'],
            'sent_to_branch'         => ['received_by_branch'],
            'received_by_branch'     => ['ready_for_customer', 'issue_open'],
            'issue_open'             => ['ready_for_customer'],
            'ready_for_customer'     => ['completed'],
            'delayed'                => ['in_preparation', 'cancelled'],
        ];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $current = $this->status instanceof CakeOrderStatus ? $this->status->value : $this->status;
        return in_array($newStatus, self::allowedTransitions()[$current] ?? []);
    }
}
