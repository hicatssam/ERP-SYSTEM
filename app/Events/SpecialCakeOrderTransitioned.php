<?php

namespace App\Events;

use App\Models\SpecialCakeOrder;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpecialCakeOrderTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SpecialCakeOrder $order,
        public readonly string           $fromStatus,
        public readonly string           $toStatus,
        public readonly User             $changedBy,
        public readonly ?string          $note = null,
    ) {}
}
