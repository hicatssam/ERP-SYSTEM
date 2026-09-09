<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ReleaseReadinessService
{
    public function __construct(
        private readonly BusinessProfileService $businessProfiles,
        private readonly ModuleService $modules,
    ) {
    }

    public function report(): array
    {
        $checks = collect([
            ...$this->clientSetupChecks(),
            ...$this->moduleChecks(),
            ...$this->databaseChecks(),
            ...$this->filesystemChecks(),
            ...$this->runtimeChecks(),
            ...$this->productionChecks(),
        ]);

        $summary = $this->summarize($checks);

        return [
            'generated_at' => now()->toIso8601String(),
            'summary' => $summary,
            'checks' => $checks->values()->all(),
            'environment' => $this->environmentSnapshot(),
            'client' => $this->clientSnapshot(),
            'modules' => $this->moduleSnapshot(),
        ];
    }

    public function handoverManifest(): array
    {
        $report = $this->report();

        return [
            'generated_at' => $report['generated_at'],
            'system' => [
                'name' => SystemSetting::get('system_name', config('app.name')),
                'name_en' => SystemSetting::get('system_name_en'),
                'business_profile' => $this->businessProfiles->current()?->name,
                'business_profile_code' => $this->businessProfiles->currentCode(),
                'onboarding_version' => SystemSetting::get('client_onboarding_version'),
                'onboarding_completed_at' => SystemSetting::get('client_onboarding_completed_at'),
            ],
            'business' => [
                'legal_name' => SystemSetting::get('business_legal_name'),
                'registration_number' => SystemSetting::get('business_registration_number'),
                'tax_number' => SystemSetting::get('business_tax_number'),
                'phone' => SystemSetting::get('business_phone'),
                'email' => SystemSetting::get('business_email'),
                'website' => SystemSetting::get('business_website'),
                'country' => SystemSetting::get('business_country'),
                'city' => SystemSetting::get('business_city'),
                'address' => SystemSetting::get('business_address'),
            ],
            'operational' => [
                'timezone' => SystemSetting::get('timezone', config('app.timezone')),
                'base_currency' => $this->baseCurrency(),
                'order_prefix' => SystemSetting::get('order_number_prefix'),
                'invoice_prefix' => SystemSetting::get('invoice_prefix'),
                'cake_order_prefix' => SystemSetting::get('cake_order_prefix'),
                'stock_request_prefix' => SystemSetting::get('stock_request_prefix'),
            ],
            'branding' => [
                'logo' => SystemSetting::get('brand_logo'),
                'report_logo' => SystemSetting::get('brand_report_logo'),
                'favicon' => SystemSetting::get('brand_favicon'),
                'theme_primary' => SystemSetting::get('theme_primary'),
                'theme_secondary' => SystemSetting::get('theme_secondary'),
                'theme_accent' => SystemSetting::get('theme_accent'),
            ],
            'enabled_modules' => $this->modules
                ->enabled()
                ->pluck('code')
                ->values()
                ->all(),
            'release_readiness' => $report['summary'],
        ];
    }

    private function clientSetupChecks(): array
    {
        $checks = [];

        $completed = (bool) SystemSetting::get(
            'client_onboarding_completed',
            false
        );

        $checks[] = $this->check(
            'client_onboarding',
            'إعداد العميل',
            $completed ? 'ok' : 'error',
            $completed
                ? 'تم تطبيق إعداد العميل.'
                : 'إعداد العميل غير مكتمل أو لم يتم تطبيقه نهائيًا.',
            'client'
        );

        $profile = $this->businessProfiles->current();

        $checks[] = $this->check(
            'business_profile',
            'نوع النشاط',
            $profile ? 'ok' : 'error',
            $profile
                ? 'النشاط الحالي: ' . $profile->name
                : 'لا يوجد Business Profile فعال.',
            'client'
        );

        $requiredBusiness = [
            'business_legal_name' => 'الاسم القانوني',
            'business_phone' => 'هاتف المنشأة',
            'business_country' => 'الدولة',
            'business_city' => 'المدينة',
            'business_address' => 'العنوان',
        ];

        $missing = collect($requiredBusiness)
            ->filter(
                fn (string $label, string $key) =>
                    trim((string) SystemSetting::get($key, '')) === ''
            )
            ->values()
            ->all();

        $checks[] = $this->check(
            'business_information',
            'بيانات المنشأة',
            $missing === [] ? 'ok' : 'warning',
            $missing === []
                ? 'البيانات الأساسية للمنشأة مكتملة.'
                : 'حقول يفضّل استكمالها: ' . implode('، ', $missing),
            'client'
        );

        $logo = trim((string) SystemSetting::get('brand_logo', ''));

        $checks[] = $this->check(
            'branding_logo',
            'الهوية البصرية',
            $logo !== '' ? 'ok' : 'warning',
            $logo !== ''
                ? 'الشعار الرئيسي محدد.'
                : 'لا يوجد شعار رئيسي محدد.',
            'client'
        );

        $favicon = trim((string) SystemSetting::get('brand_favicon', ''));

        $checks[] = $this->check(
            'branding_favicon',
            'Favicon',
            $favicon !== '' ? 'ok' : 'warning',
            $favicon !== ''
                ? 'Favicon محدد.'
                : 'Favicon غير محدد؛ يفضّل إضافته قبل التسليم.',
            'client'
        );

        return $checks;
    }

    private function moduleChecks(): array
    {
        if (! Schema::hasTable('modules')) {
            return [
                $this->check(
                    'modules_table',
                    'نظام الوحدات',
                    'error',
                    'جدول modules غير موجود.',
                    'modules'
                ),
            ];
        }

        $checks = [];
        $missingDependencies = [];

        foreach ($this->modules->enabled() as $module) {
            $missing = $module->dependencies
                ->where('is_active', false)
                ->pluck('name')
                ->all();

            if ($missing !== []) {
                $missingDependencies[] =
                    $module->name . ' ← ' . implode('، ', $missing);
            }
        }

        $checks[] = $this->check(
            'module_dependencies',
            'اعتماديات الوحدات',
            $missingDependencies === [] ? 'ok' : 'error',
            $missingDependencies === []
                ? 'كل الوحدات المفعلة متوافقة مع اعتمادياتها.'
                : implode(' | ', $missingDependencies),
            'modules'
        );

        $profile = $this->businessProfiles->current();

        if ($profile) {
            /*
             * implemented ليست عمودًا في جدول modules.
             * حالة التنفيذ تُحسم من Module::isImplemented()
             * لذلك نجلب الوحدات الموصى بها أولًا ثم نفلترها في الذاكرة.
             */
            $recommended = $profile->modules()
                ->wherePivot('is_default', true)
                ->get()
                ->filter(
                    fn ($module) => $module->isImplemented()
                )
                ->values();

            $inactiveRecommended = $recommended
                ->filter(
                    fn ($module) => ! $module->is_active
                )
                ->pluck('name')
                ->values()
                ->all();

            $checks[] = $this->check(
                'recommended_modules',
                'وحدات النشاط الموصى بها',
                $inactiveRecommended === [] ? 'ok' : 'warning',
                $inactiveRecommended === []
                    ? 'كل الوحدات المنفذة والموصى بها لهذا النشاط مفعلة.'
                    : 'وحدات موصى بها لكنها غير مفعلة: '
                        . implode('، ', $inactiveRecommended),
                'modules'
            );
        }

        $futureActive = $this->modules->all()
            ->filter(
                fn ($module) =>
                    $module->is_active
                    && ! $module->isImplemented()
            )
            ->pluck('name')
            ->values()
            ->all();

        $checks[] = $this->check(
            'future_modules_active',
            'الوحدات المستقبلية',
            $futureActive === [] ? 'ok' : 'error',
            $futureActive === []
                ? 'لا توجد وحدة غير منفذة مفعلة.'
                : 'وحدات غير منفذة مفعلة: ' . implode('، ', $futureActive),
            'modules'
        );

        return $checks;
    }

    private function databaseChecks(): array
    {
        $checks = [];

        $criticalTables = [
            'users',
            'roles',
            'permissions',
            'system_settings',
            'modules',
            'business_profiles',
            'currencies',
            'locations',
            'products',
            'customers',
            'orders',
            'invoices',
            'payments',
            'setup_wizard_runs',
        ];

        $missing = collect($criticalTables)
            ->reject(fn (string $table) => Schema::hasTable($table))
            ->values()
            ->all();

        $checks[] = $this->check(
            'critical_tables',
            'الجداول الأساسية',
            $missing === [] ? 'ok' : 'error',
            $missing === []
                ? 'كل الجداول الأساسية موجودة.'
                : 'جداول مفقودة: ' . implode('، ', $missing),
            'database'
        );

        $pending = $this->pendingMigrations();

        $checks[] = $this->check(
            'pending_migrations',
            'Migrations',
            $pending === [] ? 'ok' : 'warning',
            $pending === []
                ? 'لا توجد migrations معلقة.'
                : 'يوجد migrations معلقة: ' . implode(' | ', $pending),
            'database'
        );

        return $checks;
    }

    private function filesystemChecks(): array
    {
        $checks = [];

        $paths = [
            storage_path(),
            storage_path('logs'),
            storage_path('framework'),
            base_path('bootstrap/cache'),
        ];

        $bad = collect($paths)
            ->filter(fn (string $path) => ! File::exists($path) || ! is_writable($path))
            ->values()
            ->all();

        $checks[] = $this->check(
            'writable_directories',
            'صلاحيات الملفات',
            $bad === [] ? 'ok' : 'error',
            $bad === []
                ? 'مجلدات Laravel الأساسية قابلة للكتابة.'
                : 'مجلدات تحتاج صلاحيات كتابة: ' . implode('، ', $bad),
            'filesystem'
        );

        $publicStorage = public_path('storage');

        $checks[] = $this->check(
            'public_storage',
            'Public Storage',
            File::exists($publicStorage) ? 'ok' : 'error',
            File::exists($publicStorage)
                ? 'public/storage موجود.'
                : 'public/storage غير موجود. شغّل php artisan storage:link.',
            'filesystem'
        );

        return $checks;
    }

    private function runtimeChecks(): array
    {
        $setting = $this->normalizeTimezone(
            (string) SystemSetting::get('timezone', '')
        );

        $config = (string) config('app.timezone');
        $php = date_default_timezone_get();

        $consistent =
            $setting !== ''
            && $setting === $config
            && $setting === $php;

        return [
            $this->check(
                'timezone_runtime',
                'المنطقة الزمنية',
                $consistent ? 'ok' : 'error',
                $consistent
                    ? 'Setting وLaravel وPHP تستخدم ' . $setting . '.'
                    : sprintf(
                        'عدم تطابق: Setting=%s, Laravel=%s, PHP=%s',
                        $setting ?: '—',
                        $config ?: '—',
                        $php ?: '—'
                    ),
                'runtime'
            ),
        ];
    }

    private function productionChecks(): array
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $url = (string) config('app.url');
        $queue = (string) config('queue.default');
        $cache = (string) config('cache.default');

        $isProduction = $env === 'production';

        return [
            $this->check(
                'production_environment',
                'بيئة التشغيل',
                $isProduction ? 'ok' : 'warning',
                $isProduction
                    ? 'APP_ENV=production.'
                    : 'النظام ما زال على بيئة ' . ($env ?: 'غير محددة') . '.',
                'production'
            ),
            $this->check(
                'production_debug',
                'Debug Mode',
                $isProduction && $debug ? 'error' : ($debug ? 'warning' : 'ok'),
                $debug
                    ? 'APP_DEBUG مفعّل. يجب إيقافه في بيئة التسليم.'
                    : 'APP_DEBUG متوقف.',
                'production'
            ),
            $this->check(
                'production_url',
                'APP_URL',
                $this->isLocalUrl($url) ? 'warning' : 'ok',
                $this->isLocalUrl($url)
                    ? 'APP_URL ما زال محليًا: ' . $url
                    : 'APP_URL يبدو عنوان نشر فعلي: ' . $url,
                'production'
            ),
            $this->check(
                'queue_driver',
                'Queue',
                $queue === 'sync' ? 'warning' : 'ok',
                $queue === 'sync'
                    ? 'QUEUE_CONNECTION=sync؛ مناسب للتطوير وليس الأفضل للإنتاج.'
                    : 'Queue driver الحالي: ' . $queue,
                'production'
            ),
            $this->check(
                'cache_driver',
                'Cache',
                $isProduction && $cache === 'file' ? 'warning' : 'ok',
                'Cache store الحالي: ' . $cache,
                'production'
            ),
        ];
    }

    private function environmentSnapshot(): array
    {
        return [
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'app_env' => config('app.env'),
            'app_debug' => (bool) config('app.debug'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'queue' => config('queue.default'),
            'cache' => config('cache.default'),
            'session' => config('session.driver'),
            'database' => config('database.default'),
        ];
    }

    private function clientSnapshot(): array
    {
        return [
            'system_name' => SystemSetting::get('system_name'),
            'business_legal_name' => SystemSetting::get('business_legal_name'),
            'business_profile_code' => $this->businessProfiles->currentCode(),
            'business_profile_name' => $this->businessProfiles->current()?->name,
            'timezone' => SystemSetting::get('timezone'),
            'base_currency' => $this->baseCurrency(),
            'onboarding_completed' => (bool) SystemSetting::get(
                'client_onboarding_completed',
                false
            ),
            'onboarding_completed_at' => SystemSetting::get(
                'client_onboarding_completed_at'
            ),
        ];
    }

    private function moduleSnapshot(): array
    {
        return [
            'total' => $this->modules->all()->count(),
            'active' => $this->modules->enabled()->pluck('code')->values()->all(),
            'inactive_implemented' => $this->modules->all()
                ->filter(
                    fn ($module) =>
                        ! $module->is_active
                        && $module->isImplemented()
                )
                ->pluck('code')
                ->values()
                ->all(),
            'future' => $this->modules->all()
                ->filter(fn ($module) => ! $module->isImplemented())
                ->pluck('code')
                ->values()
                ->all(),
        ];
    }

    private function summarize(Collection $checks): array
    {
        $counts = [
            'ok' => $checks->where('status', 'ok')->count(),
            'warning' => $checks->where('status', 'warning')->count(),
            'error' => $checks->where('status', 'error')->count(),
        ];

        $weightedTotal =
            ($counts['ok'] + $counts['warning'] + $counts['error']) * 2;

        $earned =
            ($counts['ok'] * 2)
            + ($counts['warning'] * 1);

        $score = $weightedTotal > 0
            ? (int) round(($earned / $weightedTotal) * 100)
            : 100;

        $status = match (true) {
            $counts['error'] > 0 => 'blocked',
            $counts['warning'] > 0 => 'review',
            default => 'ready',
        };

        return [
            'status' => $status,
            'score' => $score,
            'counts' => $counts,
        ];
    }

    private function pendingMigrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return ['جدول migrations غير موجود'];
        }

        $ran = DB::table('migrations')
            ->pluck('migration')
            ->all();

        $files = collect(File::glob(database_path('migrations/*.php')))
            ->map(
                fn (string $file) =>
                    pathinfo($file, PATHINFO_FILENAME)
            )
            ->values()
            ->all();

        return array_values(array_diff($files, $ran));
    }

    private function baseCurrency(): ?array
    {
        if (! Schema::hasTable('currencies')) {
            return null;
        }

        $currency = DB::table('currencies')
            ->where('is_base', true)
            ->first();

        if (! $currency) {
            return null;
        }

        return [
            'code' => $currency->code ?? null,
            'symbol' => $currency->symbol ?? null,
            'is_active' => (bool) ($currency->is_active ?? false),
        ];
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);

        return match ($timezone) {
            'Gaza/Palestine' => 'Asia/Gaza',
            default => $timezone,
        };
    }

    private function isLocalUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array(
            $host,
            ['', 'localhost', '127.0.0.1'],
            true
        );
    }

    private function check(
        string $key,
        string $title,
        string $status,
        string $message,
        string $group
    ): array {
        return compact(
            'key',
            'title',
            'status',
            'message',
            'group'
        );
    }
}