<?php

namespace App\Services;

use App\Enums\ModuleType;
use App\Models\BusinessProfile;
use App\Models\Module;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BusinessProfileService
{
    private const CURRENT_KEY = 'business_profile_code';
    private const CACHE_KEY = 'business-profile:current:v1';

    public function currentCode(): string
    {
        return (string) SystemSetting::get(self::CURRENT_KEY, 'bakery_sweets');
    }

    public function current(): ?BusinessProfile
    {
        if (! Schema::hasTable('business_profiles')) {
            return null;
        }

        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => BusinessProfile::query()
                ->where('code', $this->currentCode())
                ->where('is_active', true)
                ->first()
        );
    }

    public function setCurrent(BusinessProfile $profile, User $actor): void
    {
        if (! $profile->is_active) {
            throw ValidationException::withMessages([
                'profile' => 'ملف النشاط المحدد غير فعال.',
            ]);
        }

        $before = $this->currentCode();

        SystemSetting::query()->updateOrCreate(
            ['key' => self::CURRENT_KEY],
            [
                'value' => $profile->code,
                'type' => 'string',
                'group' => 'business',
                'label' => 'نوع النشاط',
                'description' => 'ملف النشاط التجاري الحالي للنظام.',
            ]
        );
        SystemSetting::flushCache();
        Cache::forget(self::CACHE_KEY);

        ActivityLogger::log(
            $actor->id,
            'business_profile.changed',
            'modules',
            BusinessProfile::class,
            $profile->id,
            ['code' => $before],
            ['code' => $profile->code]
        );
    }

    public function apply(
        BusinessProfile $profile,
        User $actor,
        bool $deactivateOtherIndustry = false
    ): array {
        $this->setCurrent($profile, $actor);

        $modules = app(ModuleService::class);
        $result = [
            'enabled' => [],
            'skipped' => [],
            'disabled' => [],
            'blocked' => [],
        ];

        $recommended = $profile->modules()
            ->wherePivot('is_default', true)
            ->orderByPivot('sort_order')
            ->get();

        foreach ($recommended as $module) {
            if (! $module->isImplemented()) {
                $result['skipped'][] = $module->code;
                continue;
            }

            $sub = $modules->enableWithDependencies($module, $actor);
            $result['enabled'] = array_values(array_unique(array_merge($result['enabled'], $sub['enabled'])));
            $result['skipped'] = array_values(array_unique(array_merge($result['skipped'], $sub['skipped'])));
        }

        if ($deactivateOtherIndustry) {
            $recommendedCodes = $recommended->pluck('code')->all();

            Module::query()
                ->where('is_active', true)
                ->where('is_system', false)
                ->whereIn('type', [ModuleType::INDUSTRY->value, ModuleType::OPTIONAL->value])
                ->whereNotIn('code', $recommendedCodes)
                ->orderByDesc('sort_order')
                ->get()
                ->each(function (Module $module) use ($modules, $actor, &$result): void {
                    try {
                        $modules->setEnabled($module, false, $actor);
                        $result['disabled'][] = $module->code;
                    } catch (\Throwable $e) {
                        $result['blocked'][] = [
                            'code' => $module->code,
                            'reason' => $e->getMessage(),
                        ];
                    }
                });
        }

        return $result;
    }
}
