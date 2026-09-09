<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends Model
{
    protected $fillable = ['customer_id','points_balance','lifetime_earned','lifetime_redeemed'];
    protected function casts(): array { return ['points_balance'=>'integer','lifetime_earned'=>'integer','lifetime_redeemed'=>'integer']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function transactions(): HasMany { return $this->hasMany(LoyaltyTransaction::class); }
}
