<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentArrangement;
use App\Enums\RestaurantServiceType;
use App\Models\Concerns\HasSalesChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasSalesChannel;

    protected $fillable = [
        'order_number', 'public_token', 'public_request_token',
        'location_id', 'customer_id', 'guest_name', 'guest_phone',
        'delivery_address', 'order_source', 'created_by',
        'status', 'payment_status', 'payment_arrangement',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'notes', 'confirmed_at', 'completed_at', 'cancelled_at',
        'cancellation_reason', 'cancelled_by',
        'restaurant_service_type', 'restaurant_table_id',
        'restaurant_table_session_id', 'waiter_id', 'guest_count',
        'kitchen_dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'            => 'decimal:2',
            'discount_amount'     => 'decimal:2',
            'tax_amount'          => 'decimal:2',
            'total_amount'        => 'decimal:2',
            'confirmed_at'        => 'datetime',
            'completed_at'        => 'datetime',
            'cancelled_at'        => 'datetime',
            'status'              => OrderStatus::class,
            'payment_status'      => OrderPaymentStatus::class,
            'payment_arrangement' => PaymentArrangement::class,
            'restaurant_service_type' => RestaurantServiceType::class,
            'guest_count' => 'integer',
            'kitchen_dispatched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            if (! $order->isDirty('status')) {
                return;
            }

            $nextStatus = $order->status instanceof \BackedEnum
                ? (string) $order->status->value
                : (string) $order->status;

            if ($nextStatus !== OrderStatus::Confirmed->value) {
                return;
            }

            $paymentStatus = $order->payment_status instanceof \BackedEnum
                ? (string) $order->payment_status->value
                : (string) $order->payment_status;

            if ($paymentStatus === OrderPaymentStatus::PendingPaymentVerification->value) {
                throw ValidationException::withMessages([
                    'order' => 'لا يمكن تأكيد الطلب قبل اعتماد عملية الدفع المعلقة.',
                ]);
            }
        });
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->where('order_type', 'order');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Invoice::class, 'order_id')->where('order_type', 'order');
    }

    public function restaurantTable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function restaurantTableSession(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RestaurantTableSession::class, 'restaurant_table_session_id');
    }

    public function waiter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function kitchenTickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(KitchenTicket::class);
    }

    public function activeKitchenTickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->kitchenTickets()->whereIn('status', [
            'queued',
            'preparing',
            'ready',
        ]);
    }

    public function isRestaurantOrder(): bool
    {
        return $this->restaurant_service_type !== null;
    }

    public function isCustomerMenuOrder(): bool
    {
        return $this->order_source === 'customer_menu';
    }

    public function confirmedPaidAmount(): string
    {
        return $this->payments()
            ->where('status', 'confirmed')
            ->sum('amount');
    }

    public function statusValue(): string
    {
        return $this->status instanceof \BackedEnum
            ? (string) $this->status->value
            : (string) $this->status;
    }
}
