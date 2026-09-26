<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\SystemSetting;
use Illuminate\Support\Str;

class PrintThemeService
{
    public function settings(): array
    {
        $primary = $this->color(
            SystemSetting::get(
                'print_primary_color',
                SystemSetting::get(
                    'theme_primary_color',
                    '#d6a925'
                )
            ),
            '#d6a925'
        );

        $secondary = $this->color(
            SystemSetting::get(
                'print_secondary_color',
                '#111827'
            ),
            '#111827'
        );

        $text = $this->color(
            SystemSetting::get(
                'print_text_color',
                '#1f2937'
            ),
            '#1f2937'
        );

        $template = (string) SystemSetting::get(
            'print_template',
            'modern'
        );

        $paperSize = (string) SystemSetting::get(
            'print_paper_size',
            'A4'
        );

        $baseCurrency = Currency::query()
            ->where('is_base', true)
            ->first();

        return [
            'template' => in_array(
                $template,
                ['modern', 'classic', 'minimal'],
                true
            ) ? $template : 'modern',

            'primary_color' => $primary,
            'secondary_color' => $secondary,
            'text_color' => $text,

            'paper_size' => in_array(
                $paperSize,
                ['A4', 'A5', '80mm'],
                true
            ) ? $paperSize : 'A4',

            'show_logo' => $this->bool(
                'print_show_logo',
                true
            ),

            'show_business_info' => $this->bool(
                'print_show_business_info',
                true
            ),

            'show_document_number' => $this->bool(
                'print_show_document_number',
                true
            ),

            'show_signatures' => $this->bool(
                'print_show_signatures',
                true
            ),

            'show_stamp' => $this->bool(
                'print_show_stamp',
                false
            ),

            'show_footer' => $this->bool(
                'print_show_footer',
                true
            ),

            'logo_position' => $this->logoPosition(),

            'logo_size' => min(
                180,
                max(
                    40,
                    (int) SystemSetting::get(
                        'print_logo_size',
                        90
                    )
                )
            ),

            'footer_text' => trim((string) SystemSetting::get(
                'print_footer_text',
                SystemSetting::get(
                    'brand_footer_text',
                    SystemSetting::get(
                        'invoice_footer_ar',
                        ''
                    )
                )
            )),

            'business_name' => trim((string) SystemSetting::get(
                'business_legal_name',
                SystemSetting::get(
                    'system_name',
                    config('app.name')
                )
            )),

            'business_name_en' => trim((string) SystemSetting::get(
                'system_name_en',
                ''
            )),

            'business_tagline' => trim((string) SystemSetting::get(
                'brand_tagline_ar',
                ''
            )),

            'business_phone' => trim((string) SystemSetting::get(
                'business_phone',
                SystemSetting::get('phone', '')
            )),

            'business_email' => trim((string) SystemSetting::get(
                'business_email',
                SystemSetting::get('email', '')
            )),

            'business_address' => trim((string) SystemSetting::get(
                'business_address',
                SystemSetting::get('address', '')
            )),

            'business_tax_number' => trim((string) SystemSetting::get(
                'business_tax_number',
                ''
            )),

            /*
             * الأولوية:
             * print_* الخاص بالطباعة
             * ثم Branding الحالي الموجود أصلًا بالمشروع.
             */
            'logo_src' => $this->imageSource([
                SystemSetting::get('print_logo'),
                SystemSetting::get('brand_report_logo'),
                SystemSetting::get('brand_logo'),
            ]),

            'stamp_src' => $this->imageSource([
                SystemSetting::get('print_stamp'),
                SystemSetting::get('brand_stamp'),
            ]),

            'signature_src' => $this->imageSource([
                SystemSetting::get('print_signature'),
                SystemSetting::get('brand_signature'),
            ]),

            'currency_code' => $baseCurrency?->code ?: 'ILS',
            'currency_symbol' => $baseCurrency?->symbol ?: '₪',
        ];
    }

    public function paperCss(array $settings): string
    {
        return match ($settings['paper_size']) {
            'A5' => 'size: A5 portrait; margin: 9mm;',
            '80mm' => 'size: 80mm auto; margin: 4mm;',
            default => 'size: A4 portrait; margin: 11mm;',
        };
    }

    private function bool(
        string $key,
        bool $default
    ): bool {
        return filter_var(
            SystemSetting::get(
                $key,
                $default ? 1 : 0
            ),
            FILTER_VALIDATE_BOOL
        );
    }

    private function logoPosition(): string
    {
        $value = (string) SystemSetting::get(
            'print_logo_position',
            'right'
        );

        return in_array(
            $value,
            ['right', 'center', 'left'],
            true
        ) ? $value : 'right';
    }

    private function imageSource(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            if (Str::startsWith(
                $value,
                ['data:image/']
            )) {
                return $value;
            }

            /*
             * روابط خارجية نتركها كما هي للمتصفح.
             * mPDF يفضّل ملفات محلية؛ لذلك Branding المحلي أدناه
             * يتحول إلى Data URI.
             */
            if (Str::startsWith(
                $value,
                ['http://', 'https://', '//']
            )) {
                return $value;
            }

            $normalized = ltrim(
                str_replace('\\', '/', $value),
                '/'
            );

            $relative = preg_replace(
                '#^storage/#',
                '',
                $normalized
            );

            $candidates = [
                public_path($normalized),
                public_path('storage/' . $relative),
                storage_path('app/public/' . $relative),
            ];

            foreach ($candidates as $candidate) {
                if (
                    is_string($candidate)
                    && is_file($candidate)
                ) {
                    return $this->fileToDataUri(
                        $candidate
                    );
                }
            }
        }

        return null;
    }

    private function fileToDataUri(
        string $path
    ): ?string {
        $contents = @file_get_contents($path);

        if (
            $contents === false
            || $contents === ''
        ) {
            return null;
        }

        $extension = strtolower(
            pathinfo(
                $path,
                PATHINFO_EXTENSION
            )
        );

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };

        if (! $mime) {
            return null;
        }

        return 'data:'
            . $mime
            . ';base64,'
            . base64_encode($contents);
    }

    private function color(
        mixed $value,
        string $fallback
    ): string {
        $value = is_string($value)
            ? trim($value)
            : '';

        return preg_match(
            '/^#[0-9A-Fa-f]{6}$/',
            $value
        ) ? $value : $fallback;
    }
}
