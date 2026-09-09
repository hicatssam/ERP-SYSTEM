<?php

namespace App\Policies;

use App\Enums\GoodsReceiptStatus;
use App\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->can('goods_receipts.view');
    }

    public function view(User $user, GoodsReceipt $receipt): bool
    {
        return $this->viewAny($user)
            && ($user->isAdmin() || $receipt->location_id === $user->primaryLocation()?->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->can('goods_receipts.create');
    }

    public function post(User $user, GoodsReceipt $receipt): bool
    {
        return ($user->isAdmin() || $user->can('goods_receipts.approve'))
            && ($user->isAdmin() || $receipt->location_id === $user->primaryLocation()?->id)
            && $receipt->statusValue() === GoodsReceiptStatus::Draft->value;
    }
}
