<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Superseded by 2026_08_24_190000_create_inventory_expiry_alert_tracking.
        // Keeping this migration as a no-op preserves migration history and
        // prevents a second, incompatible schema for the same table.
    }

    public function down(): void
    {
        // The canonical table belongs to the earlier tracking migration.
    }
};
