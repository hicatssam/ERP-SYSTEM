<?php

namespace App\Services\Inventory;

use App\Contracts\WhatsAppGateway;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class ExpiryWhatsAppNotifier
{
    public function __construct(
        private readonly WhatsAppGateway $whatsApp
    ) {}

    public function send(array $alert): void
    {
        if (! $this->enabled()) {
            return;
        }

        $template = (string) config(
            'services.whatsapp.expiry_template',
            'inventory_expiry_alert'
        );

        $parameters = [
            $alert['product_name'] ?? '—',
            $alert['batch_number'] ?? '—',
            $alert['location_name'] ?? '—',
            $alert['expiry_date'] ?? '—',
            $alert['days_left'] ?? '—',
            $alert['available_quantity'] ?? '—',
        ];

        foreach ($this->recipients() as $user) {
            $phone = $user->employee?->phone
                ?? $user->phone
                ?? null;

            if (! filled($phone)) {
                continue;
            }

            try {
                $this->whatsApp->sendTemplate(
                    (string) $phone,
                    $template,
                    $parameters
                );
            } catch (\Throwable $e) {
                Log::error('Expiry WhatsApp notification failed.', [
                    'user_id' => $user->id,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function enabled(): bool
    {
        return (bool) SystemSetting::get(
            'inventory_expiry_notify_whatsapp',
            false
        ) && (bool) config(
            'services.whatsapp.enabled',
            false
        );
    }

    private function recipients(): Collection
    {
        $query = User::query()->with('employee');

        if (
            class_exists(Permission::class)
            && Permission::query()
                ->where('name', 'inventory.expiry-alerts.receive')
                ->exists()
        ) {
            return $query
                ->permission('inventory.expiry-alerts.receive')
                ->get();
        }

        return $query->role('Admin')->get();
    }
}
