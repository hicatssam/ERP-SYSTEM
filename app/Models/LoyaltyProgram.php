<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoyaltyProgram extends Model
{
    protected $fillable = ['name','points_per_currency_unit','minimum_order_amount','redemption_value_per_point','minimum_redeem_points','is_active','starts_at','ends_at'];
    protected function casts(): array { return ['points_per_currency_unit'=>'decimal:4','minimum_order_amount'=>'decimal:2','redemption_value_per_point'=>'decimal:4','minimum_redeem_points'=>'integer','is_active'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime']; }
    public function scopeCurrentlyActive(Builder $query): Builder { return $query->where('is_active', true)->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now())); }
}
