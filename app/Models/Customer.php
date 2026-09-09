<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_INSTITUTION = 'institution';
    public const TYPE_COMPANY = 'company';
    public const TYPE_GOVERNMENT = 'government';

    public const SCOPE_BRANCH = 'branch';
    public const SCOPE_SELECTED = 'selected';
    public const SCOPE_GLOBAL = 'global';

    protected $fillable = [
        'location_id',
        'customer_type',
        'scope',
        'name',
        'phone',
        'secondary_phone',
        'allow_credit',
        'credit_limit',
        'billing_cycle',
        'payment_terms_days',
        'tax_number',
        'contact_person',
        'address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allow_credit' => 'boolean',
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'customer_locations')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function specialCakeOrders(): HasMany
    {
        return $this->hasMany(SpecialCakeOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function customerPayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function scopeAvailableAt(Builder $query, int $locationId): Builder
    {
        return $query->where(function (Builder $customerQuery) use ($locationId): void {
            $customerQuery
                ->where(function (Builder $branchQuery) use ($locationId): void {
                    $branchQuery
                        ->where('scope', self::SCOPE_BRANCH)
                        ->where('location_id', $locationId);
                })
                ->orWhere('scope', self::SCOPE_GLOBAL)
                ->orWhere(function (Builder $selectedQuery) use ($locationId): void {
                    $selectedQuery
                        ->where('scope', self::SCOPE_SELECTED)
                        ->whereHas('locations', function (Builder $locationQuery) use ($locationId): void {
                            $locationQuery
                                ->where('locations.id', $locationId)
                                ->where('customer_locations.is_active', true);
                        });
                });
        });
    }

    /**
     * Admin / customers.view_all can see every customer.
     * Branch users see their branch customers plus central customers enabled there.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->can('customers.view_all') || $user->can('financial.global.view')) {
            return $query;
        }

        $locationId = $user->primaryLocation()?->id;

        if (! $locationId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->availableAt((int) $locationId);
    }

    public function isAvailableAt(int $locationId): bool
    {
        if ($this->scope === self::SCOPE_GLOBAL) {
            return true;
        }

        if ($this->scope === self::SCOPE_BRANCH) {
            return (int) $this->location_id === $locationId;
        }

        if ($this->scope !== self::SCOPE_SELECTED) {
            return false;
        }

        if ($this->relationLoaded('locations')) {
            return $this->locations->contains(
                fn (Location $location): bool =>
                    (int) $location->id === $locationId
                    && (bool) $location->pivot?->is_active
            );
        }

        return $this->locations()
            ->where('locations.id', $locationId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function canBeAccessedBy(User $user): bool
    {
        if ($user->isAdmin() || $user->can('customers.view_all') || $user->can('financial.global.view')) {
            return true;
        }

        $locationId = $user->primaryLocation()?->id;

        return $locationId
            ? $this->isAvailableAt((int) $locationId)
            : false;
    }

    public function isCentral(): bool
    {
        return in_array($this->scope, [self::SCOPE_SELECTED, self::SCOPE_GLOBAL], true);
    }

    public function isInstitutional(): bool
    {
        return $this->customer_type !== self::TYPE_INDIVIDUAL;
    }

    public function typeLabel(): string
    {
        return match ($this->customer_type) {
            self::TYPE_INSTITUTION => 'مؤسسة',
            self::TYPE_COMPANY => 'شركة',
            self::TYPE_GOVERNMENT => 'جهة / قطاع',
            default => 'فرد',
        };
    }

    public function scopeLabel(): string
    {
        return match ($this->scope) {
            self::SCOPE_GLOBAL => 'جميع الفروع',
            self::SCOPE_SELECTED => 'فروع محددة',
            default => 'فرع واحد',
        };
    }

    public function billingCycleLabel(): string
    {
        return match ($this->billing_cycle) {
            'weekly' => 'أسبوعي',
            'monthly' => 'شهري',
            default => 'فوري',
        };
    }

    /**
     * Net customer balance. Positive = customer owes Dahab, negative = customer credit.
     */
    public function accountBalance(?int $locationId = null): float
    {
        $invoiceQuery = $this->invoices()
            ->where('status', 'active');

        if ($locationId !== null) {
            $invoiceQuery->where('location_id', $locationId);
        }

        $remaining = (float) $invoiceQuery->sum('remaining_amount');

        $unallocatedCredit = 0.0;

        $payments = $this->customerPayments()
            ->where('status', 'confirmed')
            ->withSum('allocations as allocated_amount', 'amount')
            ->get(['id', 'amount', 'allocation_payload']);

        foreach ($payments as $payment) {
            $scopeLocationId = $payment->allocation_payload['scope_location_id'] ?? null;

            if ($locationId !== null && (int) ($scopeLocationId ?? 0) !== (int) $locationId) {
                continue;
            }

            $unallocatedCredit += max(
                0,
                (float) $payment->amount - (float) ($payment->allocated_amount ?? 0)
            );
        }

        return round($remaining - $unallocatedCredit, 2);
    }

    public function availableCredit(): ?float
    {
        if (! $this->allow_credit || $this->credit_limit === null) {
            return null;
        }

        return round(
            max(0, (float) $this->credit_limit - max(0, $this->accountBalance())),
            2
        );
    }
}
