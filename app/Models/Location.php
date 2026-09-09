<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;




class Location extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'phone',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' =>
                'boolean',

            'type' =>
                LocationType::class,
        ];
    }

    public function scopeBranches(
        $query
    ): \Illuminate\Database\Eloquent\Builder {
        return $query->where(
            'type',
            LocationType::Branch
        );
    }

    public function scopeFactory(
        $query
    ): \Illuminate\Database\Eloquent\Builder {
        return $query->where(
            'type',
            LocationType::Factory
        );
    }

    public function scopeActive(
        $query
    ): \Illuminate\Database\Eloquent\Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    public function employees(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Employee::class,
                'employee_locations'
            )
            ->withPivot(
                'is_primary',
                'started_at',
                'ended_at'
            )
            ->withTimestamps();
    }

    public function locationProducts(): HasMany
    {
        return $this->hasMany(
            LocationProduct::class
        );
    }

    public function products(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Product::class,
                'location_products'
            )
            ->withPivot(
                'is_available',
                'local_selling_price',
                'minimum_stock_level'
            )
            ->withTimestamps();
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(
            Inventory::class
        );
    }

    public function orders(): HasMany
    {
        return $this->hasMany(
            Order::class
        );
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(
            Invoice::class
        );
    }

    public function specialCakeOrdersAsBranch(): HasMany
    {
        return $this->hasMany(
            SpecialCakeOrder::class,
            'origin_branch_id'
        );
    }

    public function specialCakeOrdersAsFactory(): HasMany
    {
        return $this->hasMany(
            SpecialCakeOrder::class,
            'factory_location_id'
        );
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(
            PurchaseOrder::class
        );
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(
            GoodsReceipt::class
        );
    }

    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(
            SupplierInvoice::class
        );
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(
            PurchaseReturn::class
        );
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(
            StockTransfer::class,
            'from_location_id'
        );
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(
            StockTransfer::class,
            'to_location_id'
        );
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(
            CashSession::class
        );
    }

    public function restaurantAreas(): HasMany
    {
        return $this->hasMany(RestaurantArea::class);
    }

    public function restaurantTables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }

    public function restaurantTableSessions(): HasMany
    {
        return $this->hasMany(RestaurantTableSession::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }

    public function paymentAccounts(): HasMany
{
    return $this->hasMany(LocationPaymentAccount::class)
        ->orderBy('sort_order')
        ->orderBy('id');
}

public function activePaymentAccounts(): HasMany
{
    return $this->hasMany(LocationPaymentAccount::class)
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('id');
}

    public function chatChannel(): HasOne
    {
        return $this
            ->hasOne(
                ChatChannel::class
            )
            ->where(
                'type',
                'branch'
            );
    }

    public function isBranch(): bool
    {
        return $this->type
            === LocationType::Branch;
    }

    public function isFactory(): bool
    {
        return $this->type
            === LocationType::Factory;
 
            }

            public function locationPaymentMethods(): HasMany
{
    return $this->hasMany(LocationPaymentMethod::class);
}


public function paymentMethods(): BelongsToMany
{
    return $this->belongsToMany(
        PaymentMethod::class,
        'location_payment_methods'
    )
        ->withPivot([
            'mobile_number',
            'account_holder_name',
            'bank_name',
            'bank_account_number',
            'iban',
            'wallet_number',
            'payment_instructions',
            'details',
            'is_active',
        ])
        ->withTimestamps();
}
}
