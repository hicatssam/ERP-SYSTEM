<?php

namespace App\Contracts;

interface WhatsAppGateway
{
    /** @return array<string, mixed> */
    public function sendTemplate(
        string $to,
        string $templateName,
        array $bodyParameters = [],
        ?string $languageCode = null
    ): array;
}
