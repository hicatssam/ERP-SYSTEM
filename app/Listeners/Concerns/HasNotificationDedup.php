<?php

namespace App\Listeners\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

trait HasNotificationDedup
{
    /**
     * Atomically claim the dedup slot for a user + fingerprint pair.
     *
     * Returns true  → caller should proceed and send the notification.
     * Returns false → a duplicate was found (DB fallback) or a concurrent
     *                 worker already claimed this slot (cache gate).
     *
     * Two-layer strategy:
     *   1. DB check — cold-cache recovery after a cache flush or restart.
     *      Prevents re-sending during the dedup window even if the cache
     *      was cleared while notifications remain in the DB.
     *   2. Cache::add — atomic setnx that ensures only one of N concurrent
     *      queue workers processing the same event proceeds to notify.
     *      Without this gate, every worker reads an empty DB (the first
     *      notification is not yet committed) and all insert a copy.
     */
    protected function claimDedup(
        User   $user,
        string $fingerprint,
        string $notificationType,
        int    $ttlSeconds,
    ): bool {
        // Layer 1: DB check for cold-cache recovery.
        if ($user->notifications()
            ->where('type', $notificationType)
            ->where('data->fingerprint', $fingerprint)
            ->where('created_at', '>=', now()->subSeconds($ttlSeconds))
            ->exists()) {
            return false;
        }

        // Layer 2: Atomic cache gate. Cache::add returns true only when the key
        // was absent, meaning this worker is the first to claim the slot.
        // Concurrent workers racing through Layer 1 will get false here.
        return Cache::add("notif_dedup_{$user->id}_{$fingerprint}", 1, $ttlSeconds);
    }
}
