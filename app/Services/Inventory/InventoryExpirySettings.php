<?php

namespace App\Services\Inventory;

use App\Models\SystemSetting;

class InventoryExpirySettings
{
    public function enabled(): bool
    {
        return $this->bool('inventory_expiry_monitoring_enabled', true);
    }

    public function databaseEnabled(): bool
    {
        return $this->bool('inventory_expiry_notify_database', true);
    }

    public function emailEnabled(): bool
    {
        return $this->bool('inventory_expiry_notify_email', true);
    }

    public function whatsAppEnabled(): bool
    {
        return $this->bool('inventory_expiry_notify_whatsapp', false);
    }

    public function blockExpiredStock(): bool
    {
        return $this->bool('inventory_expiry_block_expired_stock', true);
    }

    public function earlyDays(): int
    {
        return $this->positiveInt('inventory_expiry_alert_early_days', 60);
    }

    public function warningDays(): int
    {
        return $this->positiveInt('inventory_expiry_alert_warning_days', 30);
    }

    public function criticalDays(): int
    {
        return $this->positiveInt('inventory_expiry_alert_critical_days', 7);
    }

    public function thresholds(): array
    {
        $values = array_values(array_unique([
            $this->earlyDays(),
            $this->warningDays(),
            $this->criticalDays(),
        ]));

        rsort($values);

        return $values;
    }

    private function bool(string $key, bool $default): bool
    {
        $value = SystemSetting::get($key, $default ? '1' : '0');

        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function positiveInt(string $key, int $default): int
    {
        $value = (int) SystemSetting::get($key, $default);

        return $value > 0 ? $value : $default;
    }
}
