<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    protected $fillable = ['customer_id','location_id','user_id','type','subject','notes','next_follow_up_at','completed_at','metadata'];
    protected function casts(): array { return ['next_follow_up_at'=>'datetime','completed_at'=>'datetime','metadata'=>'array']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
