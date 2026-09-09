<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Services\BusinessProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessProfileController extends Controller
{
    public function index(
        Request $request,
        BusinessProfileService $profiles
    ): View {
        $this->ensureAdmin($request);

        return view('admin.business-profiles.index', [
            'profiles' => BusinessProfile::query()
                ->where('is_active', true)
                ->with(['modules', 'bundles.modules'])
                ->orderBy('sort_order')
                ->get(),
            'currentProfile' => $profiles->current(),
        ]);
    }

    public function apply(
        Request $request,
        BusinessProfile $businessProfile,
        BusinessProfileService $profiles
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'deactivate_other_industry' => ['nullable', 'boolean'],
        ]);

        $result = $profiles->apply(
            $businessProfile,
            $request->user(),
            (bool) ($validated['deactivate_other_industry'] ?? false)
        );

        $message = 'تم اعتماد ملف النشاط «' . $businessProfile->name . '».';

        if ($result['skipped'] !== []) {
            $message .= ' الوحدات المستقبلية بقيت غير مفعلة: ' . implode('، ', $result['skipped']) . '.';
        }

        return back()->with('success', $message);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'إعدادات نوع النشاط متاحة للإدارة العليا فقط.'
        );
    }
}
