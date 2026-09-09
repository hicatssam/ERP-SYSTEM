<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialPeriodSummary extends Model
{
    protected $fillable = [
        'financial_period_id', 'location_id',
        'gross_sales', 'discounts', 'net_sales', 'confirmed_collections',
        'refunds', 'outstanding_amount', 'invoice_count', 'order_count',
        'average_order_value', 'opening_balance', 'closing_balance',
        'generated_at', 'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'gross_sales'          => 'decimal:2',
            'discounts'            => 'decimal:2',
            'net_sales'            => 'decimal:2',
            'confirmed_collections'=> 'decimal:2',
            'refunds'              => 'decimal:2',
            'outstanding_amount'   => 'decimal:2',
            'average_order_value'  => 'decimal:2',
            'opening_balance'      => 'decimal:2',
            'closing_balance'      => 'decimal:2',
            'generated_at'         => 'datetime',
        ];
    }

    public function period(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
