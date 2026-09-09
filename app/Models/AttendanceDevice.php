<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDevice extends Model
{
    protected $fillable = [
        'code','name','vendor','model','serial_number','connection_mode',
        'ip_address','port','base_url','location_id','api_token_hash',
        'is_active','last_seen_at','last_sync_at','settings','notes','created_by',
    ];

    protected $hidden = ['api_token_hash'];

    protected function casts(): array
    {
        return [
            'is_active'=>'boolean','last_seen_at'=>'datetime','last_sync_at'=>'datetime',
            'settings'=>'array','port'=>'integer',
        ];
    }

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function mappings(): HasMany { return $this->hasMany(EmployeeBiometricMapping::class); }
    public function punches(): HasMany { return $this->hasMany(AttendancePunch::class); }
    public function syncLogs(): HasMany { return $this->hasMany(AttendanceDeviceSyncLog::class); }

    public function verifyToken(?string $plainToken): bool
    {
        if (! $plainToken || ! $this->api_token_hash) return false;

        return hash_equals(
            $this->api_token_hash,
            hash('sha256', $plainToken)
        );
    }
}
