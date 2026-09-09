<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PwaController extends Controller
{
    private const ICON_SIZES = [32, 180, 192, 512];

    public function manifest(): JsonResponse
    {
        $nameAr = trim((string) SystemSetting::get('system_name', ''));
        $nameEn = trim((string) SystemSetting::get('system_name_en', ''));
        $name = $nameAr !== '' ? $nameAr : ($nameEn !== '' ? $nameEn : config('app.name', 'Dahab ERP'));
        $themeColor = $this->validColor(SystemSetting::get('theme_primary'), '#0A2948');
        $backgroundColor = $this->validColor(SystemSetting::get('theme_background'), '#F5F7FA');
        $version = $this->brandVersion();

        return response()->json([
            'id' => '/dashboard',
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'description' => 'نظام الإدارة والتشغيل',
            'lang' => 'ar',
            'dir' => 'rtl',
            'start_url' => '/dashboard?source=pwa',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => $themeColor,
            'background_color' => $backgroundColor,
            'icons' => [
                [
                    'src' => route('pwa.icon', ['size' => 192, 'v' => $version], false),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => route('pwa.icon', ['size' => 512, 'v' => $version], false),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function icon(int $size): BinaryFileResponse
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);

        $sourcePath = $this->resolveBrandImage();

        abort_unless($sourcePath !== null, 404, 'Brand logo was not found.');

        $version = $this->brandVersion();
        $cacheDirectory = storage_path('app/pwa-icons');
        $generatedPath = $cacheDirectory . DIRECTORY_SEPARATOR . "{$version}-{$size}.png";

        if (! File::exists($generatedPath)) {
            File::ensureDirectoryExists($cacheDirectory);
            $this->generateSquareIcon($sourcePath, $generatedPath, $size);
        }

        return response()->file($generatedPath, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"' . $version . '-' . $size . '"',
        ]);
    }

    private function resolveBrandImage(): ?string
    {
        $candidates = [
            SystemSetting::get('brand_logo'),
            SystemSetting::get('brand_logo_small'),
            SystemSetting::get('brand_favicon'),
            'assets/images/logo.png',
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $relativePath = ltrim(str_replace('\\', '/', trim($candidate)), '/');
            $absolutePath = public_path($relativePath);

            if (is_file($absolutePath) && is_readable($absolutePath)) {
                return $absolutePath;
            }
        }

        return null;
    }

    private function brandVersion(): string
    {
        $sourcePath = $this->resolveBrandImage();

        return substr(hash('sha256', implode('|', [
            (string) SystemSetting::get('brand_logo', ''),
            (string) SystemSetting::get('brand_logo_small', ''),
            (string) SystemSetting::get('brand_favicon', ''),
            (string) SystemSetting::get('theme_primary', ''),
            $sourcePath ?: '',
            $sourcePath && is_file($sourcePath) ? (string) filemtime($sourcePath) : '',
            $sourcePath && is_file($sourcePath) ? (string) filesize($sourcePath) : '',
        ])), 0, 16);
    }

    private function generateSquareIcon(string $sourcePath, string $destinationPath, int $size): void
    {
        abort_unless(extension_loaded('gd'), 500, 'PHP GD extension is required to generate PWA icons.');

        $sourceBytes = file_get_contents($sourcePath);
        $source = $sourceBytes !== false ? @imagecreatefromstring($sourceBytes) : false;

        abort_unless($source !== false, 422, 'The selected branding image cannot be processed.');

        imagealphablending($source, true);
        imagesavealpha($source, true);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        [$cropX, $cropY, $cropWidth, $cropHeight] = $this->visibleBounds($source, $sourceWidth, $sourceHeight);

        $canvas = imagecreatetruecolor($size, $size);
        $themeColor = $this->validColor(SystemSetting::get('theme_primary'), '#0A2948');
        [$red, $green, $blue] = sscanf(ltrim($themeColor, '#'), '%02x%02x%02x');
        $background = imagecolorallocate($canvas, $red, $green, $blue);

        imagefill($canvas, 0, 0, $background);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);

        $safeArea = (int) round($size * 0.88);
        $scale = min($safeArea / $cropWidth, $safeArea / $cropHeight);
        $targetWidth = max(1, (int) round($cropWidth * $scale));
        $targetHeight = max(1, (int) round($cropHeight * $scale));
        $destinationX = (int) round(($size - $targetWidth) / 2);
        $destinationY = (int) round(($size - $targetHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $destinationX,
            $destinationY,
            $cropX,
            $cropY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );

        imagepng($canvas, $destinationPath, 9);
        imagedestroy($canvas);
        imagedestroy($source);
    }

    private function visibleBounds(\GdImage $image, int $width, int $height): array
    {
        $minimumX = $width;
        $minimumY = $height;
        $maximumX = 0;
        $maximumY = 0;
        $found = false;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $pixel = imagecolorat($image, $x, $y);
                $alpha = ($pixel >> 24) & 0x7F;

                if ($alpha < 120) {
                    $minimumX = min($minimumX, $x);
                    $minimumY = min($minimumY, $y);
                    $maximumX = max($maximumX, $x);
                    $maximumY = max($maximumY, $y);
                    $found = true;
                }
            }
        }

        if (! $found) {
            return [0, 0, $width, $height];
        }

        return [
            $minimumX,
            $minimumY,
            ($maximumX - $minimumX) + 1,
            ($maximumY - $minimumY) + 1,
        ];
    }

    private function validColor(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value)
            ? strtoupper($value)
            : $fallback;
    }
}
