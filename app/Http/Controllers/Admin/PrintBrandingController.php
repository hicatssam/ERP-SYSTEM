<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\PrintThemeService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PrintBrandingController extends Controller
{
    public function edit(PrintThemeService $service)
    {
        $printTheme = $service->settings();

        return view('admin.settings.print-branding', compact('printTheme'));
    }

    public function preview(PrintThemeService $service)
    {
        $printTheme = $service->settings();

        return view('admin.settings.print-branding-preview', compact('printTheme'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'print_template' => ['required', Rule::in(['modern', 'classic', 'minimal'])],
            'print_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'print_secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'print_text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'print_paper_size' => ['required', Rule::in(['A4', 'A5', '80mm'])],
            'print_logo_position' => ['required', Rule::in(['right', 'center', 'left'])],
            'print_logo_size' => ['required', 'integer', 'min:40', 'max:180'],
            'business_legal_name' => ['nullable', 'string', 'max:190'],
            'business_phone' => ['nullable', 'string', 'max:60'],
            'business_email' => ['nullable', 'email', 'max:190'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'business_tax_number' => ['nullable', 'string', 'max:100'],
            'print_footer_text' => ['nullable', 'string', 'max:500'],
            'print_logo_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'print_stamp_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'print_signature_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_print_logo' => ['nullable', 'boolean'],
            'remove_print_stamp' => ['nullable', 'boolean'],
            'remove_print_signature' => ['nullable', 'boolean'],
        ]);

        foreach ([
            'print_template',
            'print_primary_color',
            'print_secondary_color',
            'print_text_color',
            'print_paper_size',
            'print_logo_position',
            'print_logo_size',
            'business_legal_name',
            'business_phone',
            'business_email',
            'business_address',
            'business_tax_number',
            'print_footer_text',
        ] as $key) {
            SystemSetting::set($key, $data[$key] ?? '');
        }

        foreach ([
            'print_show_logo',
            'print_show_business_info',
            'print_show_document_number',
            'print_show_signatures',
            'print_show_stamp',
            'print_show_footer',
        ] as $key) {
            SystemSetting::set($key, $request->boolean($key) ? 1 : 0);
        }

        $this->saveImage($request, 'print_logo', 'print_logo_file', 'remove_print_logo');
        $this->saveImage($request, 'print_stamp', 'print_stamp_file', 'remove_print_stamp');
        $this->saveImage(
            $request,
            'print_signature',
            'print_signature_file',
            'remove_print_signature'
        );

        SystemSetting::flushCache();

        return redirect()
            ->route('settings.print-branding.edit')
            ->with('success', 'تم حفظ هوية المستندات والطباعة بنجاح.');
    }

    private function saveImage(Request $request, string $settingKey, string $input, string $removeInput): void
    {
        if ($request->boolean($removeInput)) {
            $this->deleteOwnedFile(SystemSetting::get($settingKey));
            SystemSetting::set($settingKey, '');
        }

        if (! $request->hasFile($input)) {
            return;
        }

        $file = $request->file($input);
        if (! $file instanceof UploadedFile) {
            return;
        }

        $this->deleteOwnedFile(SystemSetting::get($settingKey));

        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = $settingKey . '-' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        $stored = $file->storeAs('branding/print', $filename, 'public');

        SystemSetting::set($settingKey, 'storage/' . ltrim($stored, '/'));
    }

    private function deleteOwnedFile(mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }

        $value = trim(str_replace('\\', '/', $value));

        if (! str_starts_with($value, 'storage/branding/print/')) {
            return;
        }

        $relative = substr($value, strlen('storage/'));
        if ($relative !== '') {
            Storage::disk('public')->delete($relative);
        }
    }
}
