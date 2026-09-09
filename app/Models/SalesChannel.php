<?php

namespace App\Models;

use App\Enums\CommissionBase;
use App\Enums\DeliveryFeeRecipient;
use App\Enums\DiscountType;
use App\Enums\SalesChannelType;
use App\Enums\SettlementCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class SalesChannel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'logo',
        'description',
        'discount_type',
        'discount_value',
        'discount_funded_by_channel',
        'commission_type',
        'commission_value',
        'commission_base',
        'delivery_fee_recipient',
        'settlement_cycle',
        'settlement_days',
        'is_active',
        'api_key',
        'notes',
        'sort_order',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected function casts(): array
    {
        return [
            'type' => SalesChannelType::class,
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:3',
            'discount_funded_by_channel' => 'decimal:2',
            'commission_type' => DiscountType::class,
            'commission_value' => 'decimal:3',
            'commission_base' => CommissionBase::class,
            'delivery_fee_recipient' => DeliveryFeeRecipient::class,
            'settlement_cycle' => SettlementCycle::class,
            'settlement_days' => 'integer',
            'is_active' => 'boolean',
            'api_key' => 'encrypted',
            'sort_order' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getLogoUrlAttribute(): ?string
{
    if ($this->logo === null || trim((string) $this->logo) === '') {
        return null;
    }

    $logo = str_replace('\\', '/', trim((string) $this->logo));

    // يدعم رابطًا خارجيًا كاملًا عند الحاجة.
    if (filter_var($logo, FILTER_VALIDATE_URL)) {
        return $logo;
    }

    $logo = ltrim($logo, '/');
    $logo = preg_replace('#^(?:public/)?storage/#', '', $logo) ?? $logo;
    $logo = preg_replace('#^public/#', '', $logo) ?? $logo;

    if ($logo === '' || !Storage::disk('public')->exists($logo)) {
        return null;
    }

    // رابط نسبي: يأخذ نفس الدومين والمنفذ المفتوح حاليًا تلقائيًا.
    return '/storage/' . implode(
        '/',
        array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', $logo)
        )
    );
}

    public function discountLabel(): string
    {
        if ((float) $this->discount_value <= 0) {
            return 'بدون خصم';
        }

        return $this->discount_type === DiscountType::Percentage
            ? rtrim(rtrim((string) $this->discount_value, '0'), '.') . '%'
            : '₪' . number_format((float) $this->discount_value, 2);
    }

    public function commissionLabel(): string
    {
        if ((float) $this->commission_value <= 0) {
            return 'بدون عمولة';
        }

        return $this->commission_type === DiscountType::Percentage
            ? rtrim(rtrim((string) $this->commission_value, '0'), '.') . '%'
            : '₪' . number_format((float) $this->commission_value, 2);
    }
}