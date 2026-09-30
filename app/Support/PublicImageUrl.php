<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class PublicImageUrl
{
    private const DISK_PREFIXES = [
        'images/sweets-menu/',
        'branding/',
        'products/',
        'categories/',
        'customer-menu/',
        'customer-display/',
        'menu-banners/',
        'restaurant-menu/',
        'payment-methods/',
    ];

    public static function url(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^(https?://|//|data:image/)#i', $path)) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_contains($normalized, '..') || str_contains($normalized, "\0")) {
            return null;
        }

        foreach (['storage/app/public/', 'public/storage/', 'storage/'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }

        if (self::allowedDiskPath($normalized) && Storage::disk('public')->exists($normalized)) {
            return route('customer-menu.assets.show', ['path' => $normalized], false);
        }

        if (is_file(public_path($normalized))) {
            return asset($normalized);
        }

        return null;
    }

    public static function allowedDiskPath(string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        $prefix = collect(self::DISK_PREFIXES)
            ->first(fn (string $candidate): bool => str_starts_with($path, $candidate));

        if ($prefix === null) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, $prefix === 'images/sweets-menu/'
            ? ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif', 'avif']
            : ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'ico'], true);
    }
}
