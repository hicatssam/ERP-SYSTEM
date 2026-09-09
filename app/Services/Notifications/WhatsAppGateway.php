<?php

namespace App\Services\Notifications;

interface WhatsAppGateway
{
    public function sendExpiryAlert(
        string $phone,
        array $data
    ): void;
}