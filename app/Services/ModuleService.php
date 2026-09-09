<?php

namespace App\Services;

use App\Enums\ModuleType;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ModuleService
{
    private const CACHE_KEY = 'modules:registry:v1';

    public function get(string $moduleCode): ?Module
    {
        return $this->all()->get($moduleCode);
    }

    public function all(): Collection
    {
        if (! Schema::hasTable('modules')) {
            return collect();
        }

        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Module::query()
                ->with(['dependencies', 'dependents'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->keyBy('code')
        );
    }

    public function enabled(): Collection
    {
        return $this->all()->filter->is_active->values();
    }

    public function core(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module) => $module->type === ModuleType::CORE)
            ->values();
    }

    public function industry(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module) => $module->type === ModuleType::INDUSTRY)
            ->values();
    }

    public function optional(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module) => $module->type === ModuleType::OPTIONAL)
            ->values();
    }

    public function isEnabled(string $moduleCode): bool
    {
        /*
         * Fail-open only before Sprint tables are installed.
         * This preserves the existing Dahab installation while migration/seeding
         * is not yet complete. Chat keeps its old toggle during that window.
         */
        if (! Schema::hasTable('modules')) {
            if ($moduleCode === 'chat') {
                return (bool) SystemSetting::get('chat_enabled', true);
            }

            return true;
        }

        return (bool) $this->get($moduleCode)?->is_active;
    }

    public function isCore(string $moduleCode): bool
    {
        return (bool) $this->get($moduleCode)?->is_core;
    }

    public function require(string $moduleCode): void
    {
        abort_unless(
            $this->isEnabled($moduleCode),
            403,
            'هذه الوحدة غير مفعلة في النظام.'
        );
    }

    public function setEnabled(
        string|Module $module,
        bool $enabled,
        ?User $actor = null
    ): Module {
        $module = $module instanceof Module
            ? $module
            : Module::query()->where('code', $module)->firstOrFail();

        if ($module->is_active === $enabled) {
            return $module;
        }

        if (! $enabled && $module->is_system) {
            throw ValidationException::withMessages([
                'module' => 'لا يمكن تعطيل وحدة نظام حرجة: ' . $module->name,
            ]);
        }

        if ($enabled && ! $module->isImplemented()) {
            throw ValidationException::withMessages([
                'module' => 'الوحدة «' . $module->name . '» مسجلة للمستقبل ولم يتم تنفيذ وظائفها بعد.',
            ]);
        }

        if ($enabled) {
            $disabledDependencies = $module->dependencies()
                ->where('is_active', false)
                ->pluck('name')
                ->all();

            if ($disabledDependencies !== []) {
                throw ValidationException::withMessages([
                    'module' => 'فعّل المتطلبات أولًا: ' . implode('، ', $disabledDependencies),
                ]);
            }
        } else {
            $activeDependents = $module->dependents()
                ->where('is_active', true)
                ->pluck('name')
                ->all();

            if ($activeDependents !== []) {
                throw ValidationException::withMessages([
                    'module' => 'لا يمكن تعطيل الوحدة لأنها مطلوبة بواسطة: ' . implode('، ', $activeDependents),
                ]);
            }
        }

        $before = $module->is_active;
        $module->update(['is_active' => $enabled]);

        if ($module->code === 'chat') {
            $this->mirrorLegacyChatSetting($enabled);
        }

        $this->invalidate();

        if ($actor) {
            ActivityLogger::log(
                $actor->id,
                $enabled ? 'module.enabled' : 'module.disabled',
                'modules',
                Module::class,
                $module->id,
                ['is_active' => $before],
                ['is_active' => $enabled],
                ['module_code' => $module->code]
            );
        }

        return $module->fresh();
    }

    public function enableWithDependencies(Module $module, ?User $actor = null): array
    {
        $result = [
            'enabled' => [],
            'skipped' => [],
        ];

        $visited = [];
        $this->enableRecursively($module, $actor, $visited, $result);

        return $result;
    }

    public function applyBundle(ModuleBundle $bundle, ?User $actor = null): array
    {
        $result = ['enabled' => [], 'skipped' => []];

        foreach ($bundle->modules()->orderByPivot('sort_order')->get() as $module) {
            if (! $module->isImplemented()) {
                $result['skipped'][] = $module->code;
                continue;
            }

            $sub = $this->enableWithDependencies($module, $actor);
            $result['enabled'] = array_values(array_unique(array_merge($result['enabled'], $sub['enabled'])));
            $result['skipped'] = array_values(array_unique(array_merge($result['skipped'], $sub['skipped'])));
        }

        return $result;
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function enableRecursively(
        Module $module,
        ?User $actor,
        array &$visited,
        array &$result
    ): void {
        if (isset($visited[$module->code])) {
            return;
        }

        $visited[$module->code] = true;

        if (! $module->isImplemented()) {
            $result['skipped'][] = $module->code;
            return;
        }

        foreach ($module->dependencies as $dependency) {
            $this->enableRecursively($dependency, $actor, $visited, $result);
        }

        $fresh = Module::query()->findOrFail($module->id);

        if (! $fresh->is_active) {
            $before = false;
            $fresh->update(['is_active' => true]);

            if ($fresh->code === 'chat') {
                $this->mirrorLegacyChatSetting(true);
            }

            if ($actor) {
                ActivityLogger::log(
                    $actor->id,
                    'module.enabled',
                    'modules',
                    Module::class,
                    $fresh->id,
                    ['is_active' => $before],
                    ['is_active' => true],
                    ['module_code' => $fresh->code, 'source' => 'dependency_or_bundle']
                );
            }

            $result['enabled'][] = $fresh->code;
        }

        $this->invalidate();
    }

    private function mirrorLegacyChatSetting(bool $enabled): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'chat_enabled'],
            [
                'value' => $enabled ? '1' : '0',
                'type' => 'boolean',
                'group' => 'modules',
                'label' => 'تفعيل المحادثات الداخلية',
                'description' => 'قيمة توافق قديمة؛ المصدر الرسمي الآن هو جدول modules.',
            ]
        );

        SystemSetting::flushCache();
    }
}
