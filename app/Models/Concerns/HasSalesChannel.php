<?php

namespace App\Models\Concerns;

use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasSalesChannel
{
    public function salesChannel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class)->withTrashed();
    }
}
