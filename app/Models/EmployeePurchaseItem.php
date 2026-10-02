<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePurchaseItem extends Model
{
    protected $fillable = ['employee_purchase_id', 'product_id', 'product_name', 'quantity', 'unit_price', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function purchase(): BelongsTo { return $this->belongsTo(EmployeePurchase::class, 'employee_purchase_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
