<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id', 'title', 'kind', 'path', 'original_name', 'mime_type',
        'expires_on', 'notes', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
