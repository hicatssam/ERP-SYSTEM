<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = ['customer_id','label','recipient_name','phone','address_line1','address_line2','city','area','landmark','latitude','longitude','is_default','is_active','created_by'];
    protected function casts(): array { return ['latitude'=>'decimal:7','longitude'=>'decimal:7','is_default'=>'boolean','is_active'=>'boolean']; }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function displayAddress(): string { return collect([$this->address_line1,$this->address_line2,$this->area,$this->city,$this->landmark])->filter()->implode('، '); }
}
