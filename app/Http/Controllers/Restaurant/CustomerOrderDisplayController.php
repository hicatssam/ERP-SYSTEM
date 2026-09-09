<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Restaurant\CustomerOrderDisplayService;
use App\Services\Restaurant\RestaurantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerOrderDisplayController extends Controller
{
    public function __construct(
        private readonly RestaurantContextService $context,
        private readonly CustomerOrderDisplayService $display
    ) {
    }

    public function index(Request $request): View
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $locations = $this->context->selectableLocations(
            $request->user()
        );

        /*
        |--------------------------------------------------------------------------
        | Branding
        |--------------------------------------------------------------------------
        |
        | هوية شاشة العملاء لا تملك اسم/شعار مستقل.
        | نفس Branding المستخدم في layouts.app هو المصدر الوحيد:
        |
        | system_name
        | system_name_en
        | brand_logo
        | brand_logo_small
        | brand_favicon
        | brand_tagline_ar
        | brand_tagline_en
        | brand_footer_text
        |
        */
        $branding = $this->resolveBranding();

        /*
        |--------------------------------------------------------------------------
        | Customer Display Theme
        |--------------------------------------------------------------------------
        |
        | هذه القيم تخص "شكل الشاشة" فقط ولا تكرر بيانات الهوية.
        |
        */
        $theme = [
            'backgroundImage' => SystemSetting::get(
                'customer_display_background_image',
                ''
            ),

            'backgroundColor' => SystemSetting::get(
                'customer_display_background_color',
                '#090909'
            ),

            'overlayColor' => SystemSetting::get(
                'customer_display_overlay_color',
                '#000000'
            ),

            'overlayOpacity' => $this->boundedInt(
                'customer_display_overlay_opacity',
                72,
                0,
                95
            ),

            'headerBg' => SystemSetting::get(
                'customer_display_header_bg',
                '#090909'
            ),

            'panelBg' => SystemSetting::get(
                'customer_display_panel_bg',
                '#111111'
            ),

            'cardBg' => SystemSetting::get(
                'customer_display_card_bg',
                '#181818'
            ),

            'textColor' => SystemSetting::get(
                'customer_display_text_color',
                '#FFFFFF'
            ),

            'mutedColor' => SystemSetting::get(
                'customer_display_muted_color',
                '#A3A3A3'
            ),

            'preparingColor' => SystemSetting::get(
                'customer_display_preparing_color',
                '#F0B429'
            ),

            'readyColor' => SystemSetting::get(
                'customer_display_ready_color',
                '#24C36B'
            ),

            'accentColor' => SystemSetting::get(
                'customer_display_accent_color',
                '#D7A51D'
            ),

            'borderColor' => SystemSetting::get(
                'customer_display_border_color',
                '#2A2A2A'
            ),

            'panelOpacity' => $this->boundedInt(
                'customer_display_panel_opacity',
                92,
                35,
                100
            ),

            'glassBlur' => $this->boundedInt(
                'customer_display_glass_blur',
                8,
                0,
                30
            ),

            'radius' => $this->boundedInt(
                'customer_display_radius',
                22,
                0,
                40
            ),

            'logoSize' => $this->boundedInt(
                'customer_display_logo_size',
                54,
                32,
                120
            ),

            'orderNumberSize' => $this->boundedInt(
                'customer_display_order_number_size',
                70,
                36,
                120
            ),

            'showServiceType' => $this->boolSetting(
                'customer_display_show_service_type',
                true
            ),

            'showTable' => $this->boolSetting(
                'customer_display_show_table',
                true
            ),

            'showClock' => $this->boolSetting(
                'customer_display_show_clock',
                true
            ),
        ];

        return view(
            'restaurant.customer-display.index',
            [
                'location' => $location,
                'locations' => $locations,

                'pollSeconds' => max(
                    2,
                    min(
                        30,
                        (int) SystemSetting::get(
                            'customer_display_poll_seconds',
                            4
                        )
                    )
                ),

                'branding' => $branding,
                'theme' => $theme,
            ]
        );
    }

    public function feed(Request $request): JsonResponse
    {
        $location = $this->context->resolveLocation(
            $request->user(),
            $request->integer('location_id') ?: null
        );

        $payload = $this->display->feedForLocation(
            (int) $location->id
        );

        return response()
            ->json([
                ...$payload,

                'location' => [
                    'id' => (int) $location->id,
                    'name' => (string) $location->name,
                    'code' => (string) ($location->code ?? ''),
                ],

                'server_time' => now()->toIso8601String(),
            ])
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            )
            ->header('Pragma', 'no-cache');
    }

    /**
     * نفس منطق Branding الموجود في layouts.app.
     *
     * @return array{
     *     name:string,
     *     nameEn:string,
     *     taglineAr:string,
     *     taglineEn:string,
     *     footerText:string,
     *     logoUrl:?string,
     *     logoSmallUrl:?string,
     *     faviconUrl:?string
     * }
     */
    private function resolveBranding(): array
    {
        $brandNameAr = trim(
            (string) SystemSetting::get(
                'system_name',
                ''
            )
        );

        $brandNameEn = trim(
            (string) SystemSetting::get(
                'system_name_en',
                ''
            )
        );

        /*
         * لا نستخدم أي اسم قديم ثابت داخل الكود.
         * إذا كان الاسم العربي فارغاً نعرض الاسم الإنجليزي،
         * وإذا كان الاثنان فارغين نستخدم اسم محايد فقط.
         */
        $brandName =
            $brandNameAr !== ''
                ? $brandNameAr
                : (
                    $brandNameEn !== ''
                        ? $brandNameEn
                        : 'اسم النظام'
                );

        $brandLogoPath = trim(
            (string) SystemSetting::get(
                'brand_logo',
                ''
            )
        );

        $brandLogoSmallPath = trim(
            (string) SystemSetting::get(
                'brand_logo_small',
                ''
            )
        );

        $brandFaviconPath = trim(
            (string) SystemSetting::get(
                'brand_favicon',
                ''
            )
        );

        $brandLogoUrl = $this->assetFromSetting(
            $brandLogoPath
        );

        $brandLogoSmallUrl = $this->assetFromSetting(
            $brandLogoSmallPath
        );

        /*
         * نفس fallback الموجود في layouts.app:
         * logo → small logo → public/assets/images/logo.png
         */
        $brandLogoUrl ??= $brandLogoSmallUrl;

        if (
            ! $brandLogoUrl
            && file_exists(
                public_path(
                    'assets/images/logo.png'
                )
            )
        ) {
            $brandLogoUrl = asset(
                'assets/images/logo.png'
            );
        }

        $brandLogoSmallUrl ??= $brandLogoUrl;

        return [
            'name' => $brandName,
            'nameEn' => $brandNameEn,

            'taglineAr' => (string)
                SystemSetting::get(
                    'brand_tagline_ar',
                    ''
                ),

            'taglineEn' => (string)
                SystemSetting::get(
                    'brand_tagline_en',
                    ''
                ),

            'footerText' => (string)
                SystemSetting::get(
                    'brand_footer_text',
                    ''
                ),

            'logoUrl' => $brandLogoUrl,
            'logoSmallUrl' => $brandLogoSmallUrl,

            'faviconUrl' => $this->assetFromSetting(
                $brandFaviconPath
            ),
        ];
    }

    private function assetFromSetting(
        ?string $path
    ): ?string {
        $path = trim(
            (string) $path
        );

        if ($path === '') {
            return null;
        }

        if (
            str_starts_with(
                $path,
                'http://'
            )
            || str_starts_with(
                $path,
                'https://'
            )
            || str_starts_with(
                $path,
                '//'
            )
        ) {
            return $path;
        }

        return asset(
            ltrim($path, '/')
        );
    }

    private function boundedInt(
        string $key,
        int $default,
        int $min,
        int $max
    ): int {
        return max(
            $min,
            min(
                $max,
                (int) SystemSetting::get(
                    $key,
                    $default
                )
            )
        );
    }

    private function boolSetting(
        string $key,
        bool $default
    ): bool {
        $value = SystemSetting::get(
            $key,
            $default ? '1' : '0'
        );

        return in_array(
            strtolower(
                (string) $value
            ),
            [
                '1',
                'true',
                'yes',
                'on',
            ],
            true
        );
    }
}
