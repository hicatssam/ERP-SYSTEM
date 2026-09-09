<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class AutoloadHygieneTest extends TestCase
{
    public function test_composer_autoload_contains_no_eligible_duplicate_classes(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
        $excluded = collect($composer['autoload']['exclude-from-classmap'] ?? [])
            ->map(fn (string $path): string => str_replace('\\', '/', base_path(ltrim($path, '/\\'))))
            ->all();

        $classes = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            if (collect($excluded)->contains(
                fn (string $excludedPath): bool => $path === $excludedPath
                    || str_starts_with($path, rtrim($excludedPath, '/') . '/')
            )) {
                continue;
            }

            $contents = file_get_contents($path);
            preg_match('/namespace\s+([^;]+);/', $contents, $namespace);
            preg_match('/(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $contents, $class);

            if (! isset($namespace[1], $class[1])) {
                continue;
            }

            $classes[trim($namespace[1]) . '\\' . $class[1]][] = $path;
        }

        $duplicates = array_filter($classes, fn (array $paths): bool => count($paths) > 1);

        self::assertSame([], $duplicates, 'Duplicate autoload classes: ' . json_encode($duplicates));
    }

    public function test_expiry_scheduler_uses_the_registered_command(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $consoleRoutes = file_get_contents(base_path('routes/console.php'));

        self::assertStringContainsString("inventory:check-expiry", $bootstrap);
        self::assertStringNotContainsString("inventory:scan-expiry", $consoleRoutes);
    }
}
