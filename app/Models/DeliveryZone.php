<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryZone extends Model
{
    protected $fillable = ['location_id','name','code','fee','minimum_order_amount','estimated_minutes','is_active'];
    protected function casts(): array { return ['fee'=>'decimal:2','minimum_order_amount'=>'decimal:2','estimated_minutes'=>'integer','is_active'=>'boolean']; }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function scopeActive(Builder $query): Builder { return $query->where('is_active', true); }
}
