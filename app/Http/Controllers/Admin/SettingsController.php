<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\ModuleService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private const VALUE_KEYS = [
        'system_name','timezone','invoice_footer_ar','invoice_footer_en','deposit_policy_enabled','deposit_type',
        'minimum_deposit_percentage','minimum_deposit_amount','factory_can_accept_unpaid_cake_orders',
        'require_active_cash_session_for_cash_payment','block_financial_period_close_with_open_cash_sessions',
        'notification_sound_enabled','chat_enabled','order_number_prefix','cake_order_prefix','stock_request_prefix','invoice_prefix',
        'system_name_en','brand_tagline_ar','brand_tagline_en','brand_footer_text',

        // Business
        'business_legal_name',
        'business_registration_number',
        'business_tax_number',
        'business_phone',
        'business_email',
        'business_website',
        'business_country',
        'business_city',
        'business_address',

        'system_theme_preset',
        'theme_primary','theme_secondary','theme_accent','theme_background','theme_surface','theme_text','theme_text_muted',
        'theme_border','theme_sidebar_bg','theme_sidebar_text','theme_sidebar_active','theme_sidebar_footer_bg',
        'theme_sidebar_footer_text','theme_sidebar_footer_muted','theme_sidebar_footer_border','theme_sidebar_footer_icon',
        'theme_header_bg','theme_success','theme_warning','theme_danger','theme_info','theme_font_family','theme_radius',

        // Customer Order Display
        'customer_display_background_color',
        'customer_display_overlay_color',
        'customer_display_overlay_opacity',
        'customer_display_header_bg',
        'customer_display_panel_bg',
        'customer_display_card_bg',
        'customer_display_text_color',
        'customer_display_muted_color',
        'customer_display_preparing_color',
        'customer_display_ready_color',
        'customer_display_accent_color',
        'customer_display_border_color',
        'customer_display_panel_opacity',
        'customer_display_glass_blur',
        'customer_display_radius',
        'customer_display_logo_size',
        'customer_display_order_number_size',
        'customer_display_show_service_type',
        'customer_display_show_table',
        'customer_display_show_clock',

        // Customer QR menu
        'customer_menu_primary','customer_menu_accent','customer_menu_background','customer_menu_surface',
        'customer_menu_text','customer_menu_muted','customer_menu_radius','customer_menu_columns',
        'customer_menu_hero_height','customer_menu_show_hero',
        'customer_menu_enabled','customer_menu_experience_preset','customer_menu_title','customer_menu_subtitle',
        'customer_menu_primary_color','customer_menu_accent_color','customer_menu_background_color','customer_menu_surface_color',
        'customer_menu_text_color','customer_menu_muted_color','customer_menu_border_color','customer_menu_content_width',
        'customer_menu_section_gap','customer_menu_product_card_radius','customer_menu_product_card_shadow',
        'customer_menu_nav_style','customer_menu_font_family','customer_menu_card_image_ratio','customer_menu_image_fit',
        'customer_menu_show_featured','customer_menu_featured_title','customer_menu_featured_limit','customer_menu_cover_overlay',
        'customer_menu_intro_mode','customer_menu_intro_align','customer_menu_intro_eyebrow','customer_menu_intro_title',
        'customer_menu_intro_subtitle','customer_menu_intro_cta','customer_menu_intro_show_logo','customer_menu_intro_duration',
        'customer_menu_showcase_enabled','customer_menu_showcase_dark_color','customer_menu_showcase_eyebrow',
        'customer_menu_showcase_title','customer_menu_showcase_subtitle','customer_menu_showcase_primary_cta',
        'customer_menu_showcase_secondary_cta','customer_menu_showcase_height','customer_menu_showcase_diagonal_depth',
        'customer_menu_showcase_show_info_cards','customer_menu_showcase_show_category_strip',
        'customer_menu_show_team','customer_menu_team_limit','customer_menu_team_title','customer_menu_team_subtitle',
        'customer_menu_team_card_style','customer_menu_allow_dine_in','customer_menu_allow_takeaway',
        'customer_menu_allow_delivery','customer_menu_show_search','customer_menu_show_categories',
        'customer_menu_show_descriptions','customer_menu_checkout_button_text','customer_menu_show_footer',
        'customer_menu_footer_text','customer_menu_status_sound_enabled','customer_menu_status_toast_enabled',
        'customer_menu_ad_enabled','customer_menu_ad_eyebrow','customer_menu_ad_title','customer_menu_ad_subtitle',
        'customer_menu_ad_cta','customer_menu_discount_enabled','customer_menu_discount_eyebrow',
        'customer_menu_discount_title','customer_menu_discount_subtitle','customer_menu_discount_badge',
        'customer_menu_discount_cta',

        // Chat
        'chat_theme_preset','chat_message_sound_enabled','chat_message_sound_volume','chat_background_color',
        'chat_background_overlay','chat_channels_bg','chat_channels_header_bg','chat_channel_active_bg','chat_channel_text',
        'chat_channel_muted','chat_conversation_header_bg','chat_message_mine_bg','chat_message_mine_text',
        'chat_message_other_bg','chat_message_other_text','chat_composer_bg','chat_input_bg','chat_input_text','chat_border',
        'chat_accent','chat_send_button_bg','chat_send_button_text','chat_unread_badge_bg','chat_unread_badge_text','chat_bubble_radius',
    ];

    private const FILE_KEYS = [
        'brand_logo','brand_logo_small','brand_favicon','brand_report_logo','brand_stamp','brand_signature',
        'brand_login_background','chat_background_image',

        // Customer Order Display
        'customer_display_background_image',
        'customer_menu_cover',
        'customer_menu_cover_image','customer_menu_intro_image','customer_menu_ad_image','customer_menu_discount_image',
    ];

    public function index()
    {
        $settings = SystemSetting::query()
            ->orderByRaw("
                CASE `group`
                    WHEN 'branding' THEN 1
                    WHEN 'theme' THEN 2
                    WHEN 'customer_display' THEN 3
                    WHEN 'customer_menu' THEN 4
                    WHEN 'chat' THEN 5
                    WHEN 'modules' THEN 5
                    WHEN 'general' THEN 6
                    WHEN 'financial' THEN 7
                    WHEN 'cake_orders' THEN 8
                    WHEN 'cashier' THEN 9
                    WHEN 'orders' THEN 10
                    WHEN 'invoices' THEN 11
                    WHEN 'inventory' THEN 12
                    WHEN 'notifications' THEN 13
                    ELSE 99
                END
            ")
            ->orderBy('key')
            ->get()
            ->groupBy('group');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request, ModuleService $modules)
    {
        $hex = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        $request->validate([
            'chat_enabled' => ['nullable', 'boolean'],

            'system_name' => ['nullable', 'string', 'max:120'],
            'system_name_en' => ['nullable', 'string', 'max:120'],
            'brand_tagline_ar' => ['nullable', 'string', 'max:180'],
            'brand_tagline_en' => ['nullable', 'string', 'max:180'],
            'brand_footer_text' => ['nullable', 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | Business
            |--------------------------------------------------------------------------
            */
            'business_legal_name' => [
                'nullable',
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

            'system_theme_preset' => [
                'nullable',
                Rule::in([
                    'custom',
                    'dahab-gold',
                    'midnight',
                    'emerald',
                    'ocean',
                    'rose',
                    'graphite',
                ]),
            ],

            'theme_primary' => $hex,
            'theme_secondary' => $hex,
            'theme_accent' => $hex,
            'theme_background' => $hex,
            'theme_surface' => $hex,
            'theme_text' => $hex,
            'theme_text_muted' => $hex,
            'theme_border' => $hex,
            'theme_sidebar_bg' => $hex,
            'theme_sidebar_text' => $hex,
            'theme_sidebar_active' => $hex,
            'theme_sidebar_footer_bg' => $hex,
            'theme_sidebar_footer_text' => $hex,
            'theme_sidebar_footer_muted' => $hex,
            'theme_sidebar_footer_border' => $hex,
            'theme_sidebar_footer_icon' => $hex,
            'theme_header_bg' => $hex,
            'theme_success' => $hex,
            'theme_warning' => $hex,
            'theme_danger' => $hex,
            'theme_info' => $hex,

            'theme_font_family' => [
                'nullable',
                Rule::in([
                    'Cairo',
                    'Tajawal',
                    'Arial',
                    'Tahoma',
                ]),
            ],
            'theme_radius' => [
                'nullable',
                'integer',
                'min:0',
                'max:30',
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer Order Display
            |--------------------------------------------------------------------------
            */
            'customer_display_background_color' => $hex,
            'customer_display_overlay_color' => $hex,
            'customer_display_header_bg' => $hex,
            'customer_display_panel_bg' => $hex,
            'customer_display_card_bg' => $hex,
            'customer_display_text_color' => $hex,
            'customer_display_muted_color' => $hex,
            'customer_display_preparing_color' => $hex,
            'customer_display_ready_color' => $hex,
            'customer_display_accent_color' => $hex,
            'customer_display_border_color' => $hex,

            'customer_display_overlay_opacity' => [
                'nullable',
                'integer',
                'min:0',
                'max:95',
            ],
            'customer_display_panel_opacity' => [
                'nullable',
                'integer',
                'min:35',
                'max:100',
            ],
            'customer_display_glass_blur' => [
                'nullable',
                'integer',
                'min:0',
                'max:30',
            ],
            'customer_display_radius' => [
                'nullable',
                'integer',
                'min:0',
                'max:40',
            ],
            'customer_display_logo_size' => [
                'nullable',
                'integer',
                'min:32',
                'max:120',
            ],
            'customer_display_order_number_size' => [
                'nullable',
                'integer',
                'min:36',
                'max:120',
            ],
            'customer_display_show_service_type' => [
                'nullable',
                'boolean',
            ],
            'customer_display_show_table' => [
                'nullable',
                'boolean',
            ],
            'customer_display_show_clock' => [
                'nullable',
                'boolean',
            ],
            'customer_display_background_image' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'max:12288',
            ],
            'customer_menu_primary' => $hex,
            'customer_menu_accent' => $hex,
            'customer_menu_background' => $hex,
            'customer_menu_surface' => $hex,
            'customer_menu_text' => $hex,
            'customer_menu_muted' => $hex,
            'customer_menu_radius' => ['nullable', 'integer', 'min:0', 'max:40'],
            'customer_menu_columns' => ['nullable', 'integer', 'min:2', 'max:5'],
            'customer_menu_hero_height' => ['nullable', 'integer', 'min:220', 'max:720'],
            'customer_menu_show_hero' => ['nullable', 'boolean'],
            'customer_menu_cover' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
            'customer_menu_cover_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
            'customer_menu_intro_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
            'customer_menu_ad_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
            'customer_menu_discount_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
            'customer_menu_intro_duration' => ['nullable', 'integer', 'min:600', 'max:6000'],

            /*
            |--------------------------------------------------------------------------
            | Chat
            |--------------------------------------------------------------------------
            */
            'chat_theme_preset' => [
                'nullable',
                Rule::in([
                    'whatsapp-soft',
                    'emerald',
                    'midnight',
                    'rose',
                ]),
            ],
            'chat_message_sound_enabled' => [
                'nullable',
                'boolean',
            ],
            'chat_message_sound_volume' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'chat_background_color' => $hex,
            'chat_channels_bg' => $hex,
            'chat_channels_header_bg' => $hex,
            'chat_channel_active_bg' => $hex,
            'chat_channel_text' => $hex,
            'chat_channel_muted' => $hex,
            'chat_conversation_header_bg' => $hex,
            'chat_message_mine_bg' => $hex,
            'chat_message_mine_text' => $hex,
            'chat_message_other_bg' => $hex,
            'chat_message_other_text' => $hex,
            'chat_composer_bg' => $hex,
            'chat_input_bg' => $hex,
            'chat_input_text' => $hex,
            'chat_border' => $hex,
            'chat_accent' => $hex,
            'chat_send_button_bg' => $hex,
            'chat_send_button_text' => $hex,
            'chat_unread_badge_bg' => $hex,
            'chat_unread_badge_text' => $hex,
            'chat_background_overlay' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
            'chat_bubble_radius' => [
                'nullable',
                'integer',
                'min:0',
                'max:30',
            ],

            /*
            |--------------------------------------------------------------------------
            | Existing Branding Files
            |--------------------------------------------------------------------------
            */
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
                'max:2048',
            ],
            'brand_favicon' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,webp,ico',
                'max:1024',
            ],
            'brand_report_logo' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'max:4096',
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
            'brand_login_background' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'max:8192',
            ],
            'chat_background_image' => [
                'nullable',
                'file',
                'mimes:png,jpg,jpeg,webp',
                'max:8192',
            ],
        ], [
            'regex' =>
                'يجب أن تكون قيمة اللون بصيغة HEX مثل #0A2948.',
        ]);

        foreach (self::VALUE_KEYS as $key) {
            if ($request->has($key)) {
                SystemSetting::set(
                    $key,
                    $request->input($key)
                );
            }
        }

        foreach (self::FILE_KEYS as $key) {
            if (
                $request->boolean(
                    "remove_{$key}"
                )
            ) {
                $this->removeBrandFile($key);
            }

            if ($request->hasFile($key)) {
                $this->replaceBrandFile(
                    $key,
                    $request->file($key)
                );
            }
        }

        if ($request->has('chat_enabled')) {
            $modules->setEnabled(
                'chat',
                $request->boolean(
                    'chat_enabled'
                )
            );
        }

        SystemSetting::flushCache();

        return back()->with(
            'success',
            'تم حفظ إعدادات النظام بنجاح.'
        );
    }

    private function replaceBrandFile(
        string $key,
        UploadedFile $file
    ): void {
        $this->removeBrandFile($key);

        $extension = strtolower(
            $file->getClientOriginalExtension()
            ?: 'png'
        );

        $filename =
            $key
            . '-'
            . now()->format('YmdHis')
            . '.'
            . $extension;

        $folder = match (true) {
            str_starts_with(
                $key,
                'chat_'
            ) => 'chat',

            str_starts_with(
                $key,
                'customer_display_'
            ) => 'customer-display',

            str_starts_with($key, 'customer_menu_') => 'customer-menu',

            default => 'branding',
        };

        $storedPath = $file->storeAs(
            $folder,
            $filename,
            'public'
        );

        SystemSetting::set(
            $key,
            'storage/'
            . ltrim(
                $storedPath,
                '/'
            )
        );
    }

    private function removeBrandFile(
        string $key
    ): void {
        $current =
            SystemSetting::get($key);

        if (
            is_string($current)
            && str_starts_with(
                $current,
                'storage/'
            )
        ) {
            $diskPath = substr(
                $current,
                strlen('storage/')
            );

            if ($diskPath !== '') {
                Storage::disk('public')
                    ->delete($diskPath);
            }
        }

        SystemSetting::set($key, '');
    }
}
