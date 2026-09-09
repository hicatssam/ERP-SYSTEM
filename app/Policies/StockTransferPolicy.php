<?php

namespace App\Policies;

use App\Enums\StockTransferStatus;
use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('stock_transfers.view') || $user->can('stock_transfers.dispatch') || $user->can('stock_transfers.receive');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin()
                || $transfer->from_location_id === $user->primaryLocation()?->id
                || $transfer->to_location_id === $user->primaryLocation()?->id);
    }

    public function dispatch(User $user, StockTransfer $transfer): bool
    {
        return ($user->isAdmin() || $user->can('stock_transfers.dispatch'))
            && ($user->isAdmin() || $transfer->from_location_id === $user->primaryLocation()?->id)
            && $this->value($transfer) === StockTransferStatus::Draft->value;
    }

    public function receive(User $user, StockTransfer $transfer): bool
    {
        return ($user->isAdmin() || $user->can('stock_transfers.receive'))
            && ($user->isAdmin() || $transfer->to_location_id === $user->primaryLocation()?->id)
            && $this->value($transfer) === StockTransferStatus::Dispatched->value;
    }

    private function value(StockTransfer $transfer): string
    {
        return $transfer->status instanceof \BackedEnum
            ? $transfer->status->value
            : (string) $transfer->status;
    }
}
