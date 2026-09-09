<?php
namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryTask extends Model
{
    protected $fillable = ['order_id','location_id','customer_id','customer_address_id','delivery_zone_id','assigned_driver_id','status','recipient_name','recipient_phone','address_snapshot','fee_snapshot','notes','assigned_at','picked_up_at','out_for_delivery_at','delivered_at','failed_at','cancelled_at','created_by'];
    protected function casts(): array { return ['status'=>DeliveryStatus::class,'fee_snapshot'=>'decimal:2','assigned_at'=>'datetime','picked_up_at'=>'datetime','out_for_delivery_at'=>'datetime','delivered_at'=>'datetime','failed_at'=>'datetime','cancelled_at'=>'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function address(): BelongsTo { return $this->belongsTo(CustomerAddress::class, 'customer_address_id'); }
    public function zone(): BelongsTo { return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id'); }
    public function driver(): BelongsTo { return $this->belongsTo(User::class, 'assigned_driver_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function histories(): HasMany { return $this->hasMany(DeliveryTaskHistory::class); }
}
