<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLocation extends Model
{
    protected $fillable = ['employee_id', 'location_id', 'is_primary', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'started_at' => 'date',
            'ended_at'   => 'date',
        ];
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
