<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Thin wrapper around ActivityLog::create() that enforces consistent shape
 * and makes service-layer logging a one-liner.
 */
class ActivityLogger
{
    /**
     * @param  int         $userId
     * @param  string      $action      e.g. 'order.created', 'cash_session.opened'
     * @param  string      $module      e.g. 'orders', 'inventory', 'finance'
     * @param  string      $recordType  Model class short name or table name
     * @param  int|null    $recordId
     * @param  array|null  $oldValues   Snapshot before the change
     * @param  array|null  $newValues   Snapshot after the change (or created attributes)
     * @param  array|null  $metadata    Any extra context (location_id, reason, etc.)
     * @param  string|null $ipAddress
     */
    public static function log(
        int $userId,
        string $action,
        string $module,
        string $recordType,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $ipAddress = null
    ): void {
        try {
            ActivityLog::create([
                'user_id'     => $userId,
                'action'      => $action,
                'module'      => $module,
                'record_type' => $recordType,
                'record_id'   => $recordId,
                'old_values'  => $oldValues,
                'new_values'  => $newValues,
                'metadata'    => $metadata,
                'ip_address'  => $ipAddress ?? request()?->ip(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Never let audit logging crash a business transaction.
        }
    }

    /**
     * Convenience: diff two attribute arrays and log the delta.
     */
    public static function logChange(
        int $userId,
        string $action,
        string $module,
        string $recordType,
        int $recordId,
        array $before,
        array $after,
        ?array $metadata = null,
        ?string $ipAddress = null
    ): void {
        // Only keep keys that actually changed
        $changedBefore = array_intersect_key($before, array_diff_assoc($before, $after));
        $changedAfter  = array_intersect_key($after,  array_diff_assoc($after,  $before));

        self::log($userId, $action, $module, $recordType, $recordId, $changedBefore ?: null, $changedAfter ?: null, $metadata, $ipAddress);
    }
}
