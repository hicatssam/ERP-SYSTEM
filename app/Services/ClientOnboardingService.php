<?php

namespace App\Services;

use App\Enums\ModuleType;
use App\Models\BusinessProfile;
use App\Models\Module;
use App\Models\SetupWizardRun;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClientOnboardingService
{
    public const TOTAL_STEPS = 6;
    public const VERSION = '9.0';

    private const FILE_SETTING_KEYS = [
        'brand_logo',
        'brand_logo_small',
        'brand_report_logo',
        'brand_favicon',
        'brand_login_background',
        'brand_stamp',
        'brand_signature',
    ];

    public function __construct(
        private readonly BusinessProfileService $businessProfiles,
        private readonly ModuleService $modules,
    ) {
    }

    public function openDraft(): ?SetupWizardRun
    {
        return SetupWizardRun::query()
            ->where('status', SetupWizardRun::STATUS_DRAFT)
            ->latest('id')
            ->first();
    }

    public function start(User $actor): SetupWizardRun
    {
        if ($draft = $this->openDraft()) {
            return $draft;
        }

        $currentProfile = $this->businessProfiles->current();

        return SetupWizardRun::query()->create([
            'status' => SetupWizardRun::STATUS_DRAFT,
            'current_step' => 1,
            'business_profile_id' => $currentProfile?->id,
            'business_profile_code' => $currentProfile?->code,
            'business_data' => $this->currentBusinessData(),
            'branding_data' => $this->currentBrandingData(),
            'operational_data' => $this->currentOperationalData(),
            'module_plan' => [
                'selected_codes' => $this->modules->enabled()->pluck('code')->values()->all(),
                'deactivate_unselected' => false,
            ],
            'temporary_files' => [],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'last_saved_at' => now(),
        ]);
    }

    public function forceStartNew(User $actor): SetupWizardRun
    {
        return SetupWizardRun::query()->create([
            'status' => SetupWizardRun::STATUS_DRAFT,
            'current_step' => 1,
            'business_profile_id' => $this->businessProfiles->current()?->id,
            'business_profile_code' => $this->businessProfiles->currentCode(),
            'business_data' => $this->currentBusinessData(),
            'branding_data' => $this->currentBrandingData(),
            'operational_data' => $this->currentOperationalData(),
            'module_plan' => [
                'selected_codes' => $this->modules->enabled()->pluck('code')->values()->all(),
                'deactivate_unselected' => false,
            ],
            'temporary_files' => [],
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'last_saved_at' => now(),
        ]);
    }

    public function saveStep(
        SetupWizardRun $run,
        int $step,
        array $payload,
        User $actor,
        array $files = []
    ): SetupWizardRun {
        $this->ensureDraft($run);

        match ($step) {
            1 => $this->saveBusinessProfileStep($run, $payload),
            2 => $this->saveBusinessDataStep($run, $payload),
            3 => $run->branding_data = array_merge(
                $run->branding_data ?? [],
                $payload,
                $this->stageBrandingFiles($run, $files)
            ),
            4 => $run->operational_data = $payload,
            5 => $run->module_plan = $payload,
            default => throw ValidationException::withMessages([
                'step' => 'خطوة الإعداد غير صحيحة.',
            ]),
        };

        $run->current_step = max(
            $run->current_step,
            min(self::TOTAL_STEPS, $step + 1)
        );
        $run->updated_by = $actor->id;
        $run->last_saved_at = now();
        $run->save();

        return $run->fresh();
    }

    public function apply(SetupWizardRun $run, User $actor): array
    {
        $this->ensureDraft($run);

        $profile = BusinessProfile::query()
            ->where('code', $run->business_profile_code)
            ->where('is_active', true)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'business_profile_code' => 'نوع النشاط المحدد غير متاح.',
            ]);
        }

        $result = DB::transaction(function () use ($run, $actor, $profile): array {
            $this->businessProfiles->setCurrent($profile, $actor);

            $this->applyBusinessSettings($run->business_data ?? []);
            $this->applyBrandingSettings($run->branding_data ?? []);
            $this->applyOperationalSettings($run->operational_data ?? []);

            $moduleResult = $this->applyModulePlan(
                $run->module_plan ?? [],
                $actor
            );

            $this->writeSetting(
                'client_onboarding_completed',
                '1',
                'boolean',
                'onboarding',
                'اكتمل إعداد العميل',
                'يشير إلى اكتمال معالج إعداد العميل.'
            );
            $this->writeSetting(
                'client_onboarding_completed_at',
                now()->toIso8601String(),
                'string',
                'onboarding',
                'وقت اكتمال إعداد العميل',
                'آخر وقت تم فيه تطبيق معالج إعداد العميل.'
            );
            $this->writeSetting(
                'client_onboarding_version',
                self::VERSION,
                'string',
                'onboarding',
                'إصدار إعداد العميل',
                'إصدار معالج الإعداد المستخدم.'
            );
            $this->writeSetting(
                'client_onboarding_run_id',
                (string) $run->id,
                'integer',
                'onboarding',
                'رقم جلسة الإعداد',
                'آخر جلسة إعداد تم تطبيقها.'
            );

            SystemSetting::flushCache();

            $run->forceFill([
                'status' => SetupWizardRun::STATUS_COMPLETED,
                'current_step' => self::TOTAL_STEPS,
                'completed_by' => $actor->id,
                'completed_at' => now(),
                'applied_at' => now(),
                'updated_by' => $actor->id,
                'last_saved_at' => now(),
            ])->save();

            return [
                'profile' => $profile->code,
                'modules' => $moduleResult,
            ];
        });

        $this->applyRuntimeTimezone(
            $run->operational_data['timezone'] ?? null
        );

        return $result;
    }

    public function cancel(SetupWizardRun $run, User $actor): void
    {
        $this->ensureDraft($run);

        foreach (($run->temporary_files ?? []) as $path) {
            if (is_string($path) && str_starts_with($path, 'branding/onboarding-')) {
                Storage::disk('public')->delete($path);
            }
        }

        $run->forceFill([
            'status' => SetupWizardRun::STATUS_CANCELLED,
            'updated_by' => $actor->id,
            'last_saved_at' => now(),
        ])->save();
    }

    public function normalizedTimezone(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        return match ($timezone) {
            '', 'Gaza/Palestine' => 'Asia/Gaza',
            default => in_array($timezone, timezone_identifiers_list(), true)
                ? $timezone
                : 'UTC',
        };
    }

    public function currentBusinessData(): array
    {
        return [
            'business_legal_name' => SystemSetting::get('business_legal_name', ''),
            'business_registration_number' => SystemSetting::get('business_registration_number', ''),
            'business_tax_number' => SystemSetting::get('business_tax_number', ''),
            'business_phone' => SystemSetting::get('business_phone', ''),
            'business_email' => SystemSetting::get('business_email', ''),
            'business_website' => SystemSetting::get('business_website', ''),
            'business_country' => SystemSetting::get('business_country', ''),
            'business_city' => SystemSetting::get('business_city', ''),
            'business_address' => SystemSetting::get('business_address', ''),
        ];
    }

    public function currentBrandingData(): array
    {
        $keys = [
            'system_name',
            'system_name_en',
            'brand_tagline_ar',
            'brand_tagline_en',
            'brand_footer_text',
            'brand_logo',
            'brand_logo_small',
            'brand_report_logo',
            'brand_favicon',
            'brand_login_background',
            'brand_stamp',
            'brand_signature',
            'system_theme_preset',
            'theme_primary',
            'theme_secondary',
            'theme_accent',
            'theme_background',
            'theme_surface',
            'theme_text',
            'theme_text_muted',
            'theme_border',
            'theme_sidebar_bg',
            'theme_sidebar_text',
            'theme_sidebar_active',
            'theme_header_bg',
            'theme_success',
            'theme_warning',
            'theme_danger',
            'theme_info',
            'theme_radius',
            'theme_font_family',
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => SystemSetting::get($key, '')])
            ->all();
    }

    public function currentOperationalData(): array
    {
        return [
            'timezone' => $this->normalizedTimezone(
                (string) SystemSetting::get('timezone', config('app.timezone', 'UTC'))
            ),
            'order_number_prefix' => SystemSetting::get('order_number_prefix', 'ORD'),
            'invoice_prefix' => SystemSetting::get('invoice_prefix', 'INV'),
            'cake_order_prefix' => SystemSetting::get('cake_order_prefix', 'CKO'),
            'stock_request_prefix' => SystemSetting::get('stock_request_prefix', 'SR'),
            'invoice_footer_ar' => SystemSetting::get('invoice_footer_ar', ''),
            'invoice_footer_en' => SystemSetting::get('invoice_footer_en', ''),
        ];
    }


    /**
     * يحفظ بيانات المنشأة في المسودة.
     *
     * إذا كان اسم النظام في المسودة ما زال هو الاسم الحي القديم
     * ولم يخصصه المستخدم بعد، نزامنه تلقائياً مع الاسم القانوني/التجاري
     * الجديد. إذا عدّل المستخدم اسم النظام صراحة في خطوة الهوية،
     * لا نقوم بالكتابة فوق اختياره.
     */
    private function saveBusinessDataStep(
        SetupWizardRun $run,
        array $payload
    ): void {
        $run->business_data = array_merge(
            $run->business_data ?? [],
            $payload
        );

        $businessName = trim((string) (
            $payload['business_legal_name'] ?? ''
        ));

        if ($businessName === '') {
            return;
        }

        $branding = $run->branding_data ?? [];

        $draftSystemName = trim((string) (
            $branding['system_name'] ?? ''
        ));

        $liveSystemName = trim((string) SystemSetting::get(
            'system_name',
            ''
        ));

        /*
         * forceStartNew() يملأ branding_data من الإعداد الحالي.
         * لذلك إذا بقي draft system_name مساويًا للاسم الحي القديم،
         * فهذا يعني أن المستخدم لم يخصصه بعد، فنحدثه تلقائياً.
         */
        if (
            $draftSystemName === ''
            || $draftSystemName === $liveSystemName
        ) {
            $branding['system_name'] = $businessName;
            $run->branding_data = $branding;
        }
    }

    private function saveBusinessProfileStep(SetupWizardRun $run, array $payload): void
    {
        $profile = BusinessProfile::query()
            ->where('code', $payload['business_profile_code'] ?? null)
            ->where('is_active', true)
            ->first();

        if (! $profile) {
            throw ValidationException::withMessages([
                'business_profile_code' => 'نوع النشاط المحدد غير متاح.',
            ]);
        }

        $run->business_profile_id = $profile->id;
        $run->business_profile_code = $profile->code;

        if (empty($run->module_plan['selected_codes'] ?? [])) {
            $recommended = $profile->modules()
                ->wherePivot('is_default', true)
                ->pluck('modules.code')
                ->all();

            $run->module_plan = [
                'selected_codes' => array_values(array_unique(array_merge(
                    $this->modules->core()->pluck('code')->all(),
                    $recommended
                ))),
                'deactivate_unselected' => false,
            ];
        }
    }

    private function stageBrandingFiles(
        SetupWizardRun $run,
        array $files
    ): array {
        $saved = [];
        $temporary = $run->temporary_files ?? [];

        foreach (self::FILE_SETTING_KEYS as $key) {
            $file = $files[$key] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
            $filename = sprintf(
                '%s-%s-%s.%s',
                $key,
                now()->format('YmdHis'),
                bin2hex(random_bytes(4)),
                $extension
            );

            $stored = $file->storeAs(
                'branding/onboarding-' . $run->id,
                $filename,
                'public'
            );

            $saved[$key] = 'storage/' . $stored;
            $temporary[] = $stored;
        }

        $run->temporary_files = array_values(array_unique($temporary));

        return $saved;
    }

    private function applyBusinessSettings(array $data): void
    {
        $map = [
            'business_legal_name' => ['الاسم القانوني للمنشأة', 'الاسم القانوني أو التجاري الرسمي.'],
            'business_registration_number' => ['رقم التسجيل', 'رقم السجل أو التسجيل التجاري.'],
            'business_tax_number' => ['الرقم الضريبي', 'الرقم الضريبي للمنشأة.'],
            'business_phone' => ['هاتف المنشأة', 'رقم الهاتف الرئيسي.'],
            'business_email' => ['بريد المنشأة', 'البريد الإلكتروني الرسمي.'],
            'business_website' => ['موقع المنشأة', 'الموقع الإلكتروني الرسمي.'],
            'business_country' => ['الدولة', 'دولة المنشأة.'],
            'business_city' => ['المدينة', 'مدينة المنشأة.'],
            'business_address' => ['العنوان', 'العنوان الرئيسي للمنشأة.'],
        ];

        foreach ($map as $key => [$label, $description]) {
            $value = (string) ($data[$key] ?? '');

            /*
             * استخدم API الإعدادات نفسه حتى يتم إسقاط cache الخاص بالمفتاح
             * فوراً، ثم حدّث metadata الخاصة بعرض الإعداد.
             */
            SystemSetting::set($key, $value);

            SystemSetting::query()
                ->where('key', $key)
                ->update([
                    'type' => 'string',
                    'group' => 'business',
                    'label' => $label,
                    'description' => $description,
                ]);
        }
    }

    private function applyBrandingSettings(array $data): void
    {
        $types = [
            'theme_radius' => 'integer',
        ];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $group = str_starts_with($key, 'theme_')
                || $key === 'system_theme_preset'
                    ? 'theme'
                    : 'branding';

            $this->writeSetting(
                $key,
                is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                $types[$key] ?? 'string',
                $group,
                $key,
                'تم تحديثه عبر معالج إعداد العميل.'
            );
        }
    }

    private function applyOperationalSettings(array $data): void
    {
        $map = [
            'timezone' => ['general', 'string', 'المنطقة الزمنية'],
            'order_number_prefix' => ['orders', 'string', 'بادئة رقم الطلب'],
            'invoice_prefix' => ['invoices', 'string', 'بادئة رقم الفاتورة'],
            'cake_order_prefix' => ['cake_orders', 'string', 'بادئة طلب الكيك'],
            'stock_request_prefix' => ['inventory', 'string', 'بادئة طلب المخزون'],
            'invoice_footer_ar' => ['invoices', 'string', 'تذييل الفاتورة بالعربية'],
            'invoice_footer_en' => ['invoices', 'string', 'تذييل الفاتورة بالإنجليزية'],
        ];

        foreach ($map as $key => [$group, $type, $label]) {
            $value = $data[$key] ?? '';

            if ($key === 'timezone') {
                $value = $this->normalizedTimezone((string) $value);
            }

            $this->writeSetting(
                $key,
                (string) $value,
                $type,
                $group,
                $label,
                'تم تحديثه عبر معالج إعداد العميل.'
            );
        }
    }

    private function applyModulePlan(array $plan, User $actor): array
    {
        $selected = array_values(array_unique(array_filter(
            $plan['selected_codes'] ?? []
        )));

        $result = [
            'enabled' => [],
            'skipped' => [],
            'disabled' => [],
            'blocked' => [],
        ];

        foreach ($selected as $code) {
            $module = $this->modules->get($code);

            if (! $module) {
                continue;
            }

            if (! $module->isImplemented()) {
                $result['skipped'][] = $code;
                continue;
            }

            $sub = $this->modules->enableWithDependencies($module, $actor);
            $result['enabled'] = array_values(array_unique(array_merge(
                $result['enabled'],
                $sub['enabled']
            )));
            $result['skipped'] = array_values(array_unique(array_merge(
                $result['skipped'],
                $sub['skipped']
            )));
        }

        if (! empty($plan['deactivate_unselected'])) {
            Module::query()
                ->where('is_active', true)
                ->where('is_system', false)
                ->whereIn('type', [
                    ModuleType::INDUSTRY->value,
                    ModuleType::OPTIONAL->value,
                ])
                ->whereNotIn('code', $selected)
                ->orderByDesc('sort_order')
                ->get()
                ->each(function (Module $module) use ($actor, &$result): void {
                    try {
                        $this->modules->setEnabled($module, false, $actor);
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

    private function writeSetting(
        string $key,
        string $value,
        string $type,
        string $group,
        string $label,
        string $description
    ): void {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'label' => $label,
                'description' => $description,
            ]
        );
    }

    private function applyRuntimeTimezone(?string $timezone): void
    {
        $timezone = $this->normalizedTimezone($timezone);

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }

    private function ensureDraft(SetupWizardRun $run): void
    {
        if (! $run->isDraft()) {
            throw ValidationException::withMessages([
                'wizard' => 'جلسة الإعداد هذه ليست مفتوحة للتعديل.',
            ]);
        }
    }
}