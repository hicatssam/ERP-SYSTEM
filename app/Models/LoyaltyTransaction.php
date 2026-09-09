<?php
namespace App\Models;

use App\Enums\LoyaltyTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    protected $fillable = ['loyalty_account_id','customer_id','order_id','type','points','balance_after','idempotency_key','note','metadata','created_by'];
    protected function casts(): array { return ['type'=>LoyaltyTransactionType::class,'points'=>'integer','balance_after'=>'integer','metadata'=>'array']; }
    public function account(): BelongsTo { return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
