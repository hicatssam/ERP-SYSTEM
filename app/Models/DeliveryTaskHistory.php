<?php
namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTaskHistory extends Model
{
    protected $fillable = ['delivery_task_id','from_status','to_status','changed_by','note','metadata'];
    protected function casts(): array { return ['from_status'=>DeliveryStatus::class,'to_status'=>DeliveryStatus::class,'metadata'=>'array']; }
    public function task(): BelongsTo { return $this->belongsTo(DeliveryTask::class, 'delivery_task_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
