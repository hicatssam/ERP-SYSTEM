<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\Currency;
use App\Models\SetupWizardRun;
use App\Models\SystemSetting;
use App\Services\BusinessProfileService;
use App\Services\ClientOnboardingService;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientOnboardingController extends Controller
{
    public function __construct(
        private readonly ClientOnboardingService $onboarding,
        private readonly ModuleService $modules,
        private readonly BusinessProfileService $businessProfiles,
    ) {
    }

    public function index(Request $request): View
    {
        $lastCompletedRun = SetupWizardRun::query()
            ->where('status', SetupWizardRun::STATUS_COMPLETED)
            ->latest('id')
            ->first();

        /*
         * لا نعتمد فقط على system_settings.
         * وجود جلسة completed/applied يعتبر أيضًا دليلاً أن الإعداد طُبق.
         */
        $completed =
            (bool) SystemSetting::get('client_onboarding_completed', false)
            || (bool) $lastCompletedRun;

        /*
         * الوضع الطبيعي بعد التطبيق:
         * /settings/onboarding
         * => يعرض الملخص الحالي.
         *
         * الـ Wizard لا يفتح إلا صراحة عبر edit=1 أو step=...
         */
        $editMode =
            $request->boolean('edit')
            || $request->has('step');

        if ($completed && ! $editMode) {
            return $this->completedView($lastCompletedRun);
        }

        $run = $this->onboarding->openDraft();

        /*
         * في أول إعداد للنظام لا يوجد completed run، فنفتح المسودة.
         * أما بعد اكتمال الإعداد فلا ننشئ Draft جديد إلا عند دخول وضع التعديل.
         */
        if (! $run) {
            $run = $completed
                ? $this->onboarding->forceStartNew($request->user())
                : $this->onboarding->start($request->user());
        }

        $requestedStep = (int) $request->integer(
            'step',
            $run->current_step
        );

        $step = max(
            1,
            min(
                ClientOnboardingService::TOTAL_STEPS,
                min($requestedStep, $run->current_step)
            )
        );

        $profiles = BusinessProfile::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $profile = $run->business_profile_code
            ? BusinessProfile::query()
                ->where('code', $run->business_profile_code)
                ->first()
            : null;

        $recommendedCodes = $profile
            ? $profile->modules()
                ->wherePivot('is_default', true)
                ->pluck('modules.code')
                ->all()
            : [];

        $allModules = $this->modules->all()->values();

        $baseCurrency = null;

        if (Schema::hasTable('currencies')) {
            $baseCurrency = Currency::query()
                ->where('is_base', true)
                ->first();
        }

        return view('admin.onboarding.index', [
            'run' => $run,
            'step' => $step,
            'profiles' => $profiles,
            'profile' => $profile,
            'recommendedCodes' => $recommendedCodes,
            'allModules' => $allModules,
            'baseCurrency' => $baseCurrency,
            'timezones' => timezone_identifiers_list(),
            'totalSteps' => ClientOnboardingService::TOTAL_STEPS,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        /*
         * "تعديل إعداد العميل" هو المدخل الوحيد لفتح الـWizard بعد التطبيق.
         * إن كانت هناك مسودة تعديل سابقة نكملها.
         */
        $run = $this->onboarding->openDraft()
            ?? $this->onboarding->forceStartNew($request->user());

        return redirect()
            ->route('onboarding.index', [
                'edit' => 1,
                'step' => max(1, (int) $run->current_step),
            ])
            ->with(
                'success',
                'تم فتح وضع تعديل إعداد العميل. لن تتغير الإعدادات الحالية حتى الضغط على "تطبيق إعداد العميل" في الخطوة الأخيرة.'
            );
    }

    public function saveStep(
        Request $request,
        SetupWizardRun $run,
        int $step
    ): RedirectResponse {
        if ($step < 1 || $step > 5) {
            abort(404);
        }

        $payload = $this->validatedStep($request, $step);

        if ($step === 5) {
            $coreCodes = $this->modules->core()
                ->filter(fn ($module) => $module->isImplemented())
                ->pluck('code')
                ->all();

            $payload['selected_codes'] = array_values(
                array_unique(
                    array_merge(
                        $coreCodes,
                        $payload['selected_codes'] ?? []
                    )
                )
            );

            $payload['deactivate_unselected'] =
                $request->boolean('deactivate_unselected');
        }

        $files = $step === 3
            ? collect([
                'brand_logo',
                'brand_logo_small',
                'brand_report_logo',
                'brand_favicon',
                'brand_login_background',
                'brand_stamp',
                'brand_signature',
            ])->mapWithKeys(
                fn (string $key) => [$key => $request->file($key)]
            )->all()
            : [];

        $this->onboarding->saveStep(
            $run,
            $step,
            $payload,
            $request->user(),
            $files
        );

        return redirect()
            ->route('onboarding.index', [
                'edit' => 1,
                'step' => min(
                    ClientOnboardingService::TOTAL_STEPS,
                    $step + 1
                ),
            ])
            ->with('success', 'تم حفظ الخطوة في المسودة.');
    }

    public function apply(
        Request $request,
        SetupWizardRun $run
    ): RedirectResponse {
        $result = $this->onboarding->apply(
            $run,
            $request->user()
        );

        /*
         * تأكيد إضافي لحالة onboarding حتى لو كان هناك Cache قديم.
         */
        SystemSetting::set('client_onboarding_completed', true);
        SystemSetting::set(
            'client_onboarding_completed_at',
            now()->toIso8601String()
        );
        SystemSetting::set(
            'client_onboarding_version',
            ClientOnboardingService::VERSION
        );
        SystemSetting::set(
            'client_onboarding_run_id',
            $run->id
        );
        SystemSetting::flushCache();

        $blocked = $result['modules']['blocked'] ?? [];

        $message = 'تم تطبيق إعداد العميل بنجاح.';

        if ($blocked !== []) {
            $message .= ' بقيت بعض الوحدات مفعلة بسبب اعتماد وحدات أخرى عليها.';
        }

        /*
         * مهم:
         * بعد التطبيق نرجع إلى /settings/onboarding بدون edit/step
         * حتى تظهر صفحة الملخص مباشرة.
         */
        return redirect()
            ->route('onboarding.index')
            ->with('success', $message);
    }

    public function destroy(
        Request $request,
        SetupWizardRun $run
    ): RedirectResponse {
        $this->onboarding->cancel(
            $run,
            $request->user()
        );

        return redirect()
            ->route('onboarding.index')
            ->with(
                'success',
                'تم إلغاء مسودة التعديل والرجوع إلى الإعداد الحالي.'
            );
    }

    private function completedView(
        ?SetupWizardRun $lastCompletedRun = null
    ): View {
        $lastCompletedRun ??= SetupWizardRun::query()
            ->where('status', SetupWizardRun::STATUS_COMPLETED)
            ->latest('id')
            ->first();

        $baseCurrency = null;

        if (Schema::hasTable('currencies')) {
            $baseCurrency = Currency::query()
                ->where('is_base', true)
                ->first();
        }

        return view('admin.onboarding.completed', [
            'lastRun' => $lastCompletedRun,
            'currentProfile' => $this->businessProfiles->current(),
            'businessData' => $this->onboarding->currentBusinessData(),
            'brandingData' => $this->onboarding->currentBrandingData(),
            'operationalData' => $this->onboarding->currentOperationalData(),
            'enabledModules' => $this->modules->enabled(),
            'baseCurrency' => $baseCurrency,
            'completedAt' => SystemSetting::get(
                'client_onboarding_completed_at'
            ),
            'onboardingVersion' => SystemSetting::get(
                'client_onboarding_version'
            ),
        ]);
    }

    private function validatedStep(
        Request $request,
        int $step
    ): array {
        return match ($step) {
            1 => $request->validate([
                'business_profile_code' => [
                    'required',
                    'string',
                    Rule::exists(
                        'business_profiles',
                        'code'
                    )->where('is_active', true),
                ],
            ]),

            2 => $request->validate([
                'business_legal_name' => [
                    'required',
                    'string',
                    'max:190',
                ],
                'business_registration_number' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'business_tax_number' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
                'business_phone' => [
                    'nullable',
                    'string',
                    'max:60',
                ],
                'business_email' => [
                    'nullable',
                    'email',
                    'max:190',
                ],
                'business_website' => [
                    'nullable',
                    'url',
                    'max:255',
                ],
                'business_country' => [
                    'nullable',
                    'string',
                    'max:120',
                ],
                'business_city' => [
                    'nullable',
                    'string',
                    'max:120',
                ],
                'business_address' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]),

            3 => $request->validate([
                'system_name' => [
                    'required',
                    'string',
                    'max:190',
                ],
                'system_name_en' => [
                    'nullable',
                    'string',
                    'max:190',
                ],
                'brand_tagline_ar' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'brand_tagline_en' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'brand_footer_text' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'system_theme_preset' => [
                    'nullable',
                    'string',
                    'max:60',
                ],
                'theme_primary' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_secondary' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_accent' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_background' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_surface' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_text' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_sidebar_bg' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_sidebar_text' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_sidebar_active' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_header_bg' => [
                    'nullable',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],
                'theme_radius' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:40',
                ],
                'theme_font_family' => [
                    'nullable',
                    'string',
                    'max:80',
                ],

                'brand_logo' => [
                    'nullable',
                    'file',
                    'mimes:png,jpg,jpeg,webp',
                    'max:4096',
                ],
                'brand_logo_small' => [
                    'nullable',
                    'file',
                    'mimes:png,jpg,jpeg,webp',
                    'max:4096',
                ],
                'brand_report_logo' => [
                    'nullable',
                    'file',
                    'mimes:png,jpg,jpeg,webp',
                    'max:4096',
                ],
                'brand_favicon' => [
                    'nullable',
                    'file',
                    'mimes:png,jpg,jpeg,webp,ico',
                    'max:2048',
                ],
                'brand_login_background' => [
                    'nullable',
                    'file',
                    'mimes:png,jpg,jpeg,webp',
                    'max:8192',
                ],
                'brand_stamp' => [
                    'nullable',
                    'file',
                    'mimes:png,jpg,jpeg,webp',
                    'max:4096',
                ],
                'brand_signature' => [
                    'nullable',
                    'file',
                    'mimes:png,jpg,jpeg,webp',
                    'max:4096',
                ],
            ]),

            4 => $request->validate([
                'timezone' => [
                    'required',
                    'string',
                    function (
                        string $attribute,
                        mixed $value,
                        \Closure $fail
                    ): void {
                        if (
                            $value !== 'Gaza/Palestine'
                            && ! in_array(
                                $value,
                                timezone_identifiers_list(),
                                true
                            )
                        ) {
                            $fail(
                                'المنطقة الزمنية المحددة غير صحيحة.'
                            );
                        }
                    },
                ],
                'order_number_prefix' => [
                    'required',
                    'regex:/^[A-Za-z0-9_-]{1,12}$/',
                ],
                'invoice_prefix' => [
                    'required',
                    'regex:/^[A-Za-z0-9_-]{1,12}$/',
                ],
                'cake_order_prefix' => [
                    'nullable',
                    'regex:/^[A-Za-z0-9_-]{1,12}$/',
                ],
                'stock_request_prefix' => [
                    'nullable',
                    'regex:/^[A-Za-z0-9_-]{1,12}$/',
                ],
                'invoice_footer_ar' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
                'invoice_footer_en' => [
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]),

            5 => $request->validate([
                'selected_codes' => [
                    'nullable',
                    'array',
                ],
                'selected_codes.*' => [
                    'string',
                    Rule::exists('modules', 'code'),
                ],
            ]),

            default => throw ValidationException::withMessages([
                'step' => 'خطوة الإعداد غير صحيحة.',
            ]),
        };
    }
}