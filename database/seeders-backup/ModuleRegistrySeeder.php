<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Models\SystemSetting;
use App\Services\ModuleService;
use App\Support\BusinessProfileRegistry;
use App\Support\ModuleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class ModuleRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $modulesByCode = [];

        foreach (ModuleRegistry::modules() as $definition) {
            $module = Module::query()->firstOrNew([
                'code' => $definition['code'],
            ]);

            $isNew = ! $module->exists;
            $legacyChatEnabled = (bool) SystemSetting::get('chat_enabled', true);

            $module->fill([
                'name' => $definition['name'],
                'description' => $definition['description'],
                'type' => $definition['type'],
                'icon' => $definition['icon'],
                'route_prefix' => $definition['route_prefix'],
                'is_core' => $definition['is_core'],
                'is_system' => $definition['is_system'],
                'sort_order' => $definition['sort_order'],
                'configuration' => [
                    'implemented' => (bool) $definition['implemented'],
                    'registered_by' => 'ModuleRegistrySeeder',
                ],
            ]);

            if ($isNew) {
                $module->is_active = $definition['code'] === 'chat'
                    ? $legacyChatEnabled
                    : (bool) $definition['initial_active'];
            }

            $module->save();
            $modulesByCode[$module->code] = $module;
        }

        foreach (ModuleRegistry::dependencies() as $moduleCode => $requiredCodes) {
            $module = $modulesByCode[$moduleCode] ?? null;
            if (! $module) {
                continue;
            }

            $ids = collect($requiredCodes)
                ->map(fn (string $code) => $modulesByCode[$code]->id ?? null)
                ->filter()
                ->values()
                ->all();

            $module->dependencies()->sync($ids);
        }

        $profilesByCode = [];

        foreach (BusinessProfileRegistry::profiles() as $definition) {
            $profile = BusinessProfile::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'icon' => $definition['icon'],
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $definition['sort_order'],
                    'configuration' => [
                        'registered_by' => 'ModuleRegistrySeeder',
                    ],
                ]
            );

            $pivot = [];
            foreach (array_values(array_unique($definition['modules'])) as $index => $code) {
                if (! isset($modulesByCode[$code])) {
                    continue;
                }

                $pivot[$modulesByCode[$code]->id] = [
                    'is_required' => (bool) $modulesByCode[$code]->is_system,
                    'is_default' => true,
                    'sort_order' => ($index + 1) * 10,
                ];
            }

            $profile->modules()->sync($pivot);
            $profilesByCode[$profile->code] = $profile;
        }

        foreach (BusinessProfileRegistry::bundles() as $definition) {
            $profileId = $definition['profile']
                ? ($profilesByCode[$definition['profile']]->id ?? null)
                : null;

            $bundle = ModuleBundle::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'business_profile_id' => $profileId,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'],
                    'configuration' => [
                        'registered_by' => 'ModuleRegistrySeeder',
                    ],
                ]
            );

            $pivot = [];
            foreach ($definition['modules'] as $index => $code) {
                if (isset($modulesByCode[$code])) {
                    $pivot[$modulesByCode[$code]->id] = [
                        'sort_order' => ($index + 1) * 10,
                    ];
                }
            }
            $bundle->modules()->sync($pivot);
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'business_profile_code'],
            [
                'value' => SystemSetting::get('business_profile_code', 'bakery_sweets'),
                'type' => 'string',
                'group' => 'business',
                'label' => 'نوع النشاط',
                'description' => 'ملف النشاط التجاري الحالي للنظام.',
            ]
        );

        SystemSetting::flushCache();
        Cache::forget('business-profile:current:v1');
        app(ModuleService::class)->invalidate();
    }
}
