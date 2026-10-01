<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkHoliday extends Model
{
    protected $fillable = ['location_id', 'holiday_date', 'name'];

    protected function casts(): array
    {
        return ['holiday_date' => 'date'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
