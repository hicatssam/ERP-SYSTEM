<?php

namespace App\Policies;

use App\Models\StockRequest;
use App\Models\User;

class StockRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->can('stock_requests.view')
            || $user->can('stock_requests.create')
            || $user->can('stock_requests.review');
    }

    public function view(User $user, StockRequest $stockRequest): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (
            ! $user->can('stock_requests.view')
            && ! $user->can('stock_requests.create')
            && ! $user->can('stock_requests.review')
        ) {
            return false;
        }

        $locationId = $user->primaryLocation()?->id;

        if (! $locationId) {
            return false;
        }

        return (int) $stockRequest->branch_location_id === (int) $locationId
            || (int) $stockRequest->factory_location_id === (int) $locationId;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->can('stock_requests.create');
    }

    public function update(User $user, StockRequest $stockRequest): bool
    {
        $status = $stockRequest->status?->value ?? $stockRequest->status;

        return (
            $user->isAdmin()
            || $user->can('stock_requests.create')
        )
            && $status === 'draft'
            && (
                $user->isAdmin()
                || (int) $stockRequest->branch_location_id
                    === (int) $user->primaryLocation()?->id
            );
    }

    public function submit(User $user, StockRequest $stockRequest): bool
    {
        return $this->update(
            $user,
            $stockRequest
        );
    }

    public function review(User $user, StockRequest $stockRequest): bool
    {
        $status = $stockRequest->status?->value ?? $stockRequest->status;

        /*
         * الخطأ القديم كان يفحص pending_review بينما النظام فعلياً
         * يستخدم pending_factory_review.
         */
        if ($status !== 'pending_factory_review') {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->can('stock_requests.review')) {
            return false;
        }

        $locationId = $user->primaryLocation()?->id;

        return $locationId
            && (int) $stockRequest->factory_location_id
                === (int) $locationId;
    }

    public function delete(User $user, StockRequest $stockRequest): bool
    {
        $status = $stockRequest->status?->value ?? $stockRequest->status;

        return $user->isAdmin()
            && $status === 'draft';
    }
}