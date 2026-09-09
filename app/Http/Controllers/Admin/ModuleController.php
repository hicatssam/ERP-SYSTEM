<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModuleType;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleBundle;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $query = Module::query()
            ->with(['dependencies'])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->trim()->toString();
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return view('admin.modules.index', [
            'modules' => $query->get(),
            'types' => ModuleType::cases(),
            'bundles' => ModuleBundle::query()
                ->where('is_active', true)
                ->with(['businessProfile', 'modules'])
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function update(
        Request $request,
        Module $module,
        ModuleService $modules
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $modules->setEnabled(
            $module,
            (bool) $validated['enabled'],
            $request->user()
        );

        return back()->with('success', 'تم تحديث حالة الوحدة بنجاح.');
    }

    public function applyBundle(
        Request $request,
        ModuleBundle $bundle,
        ModuleService $modules
    ): RedirectResponse {
        $this->ensureAdmin($request);

        $result = $modules->applyBundle($bundle, $request->user());

        $message = 'تم تطبيق الحزمة.';
        if ($result['skipped'] !== []) {
            $message .= ' بعض الوحدات مسجلة للمستقبل ولم تُفعّل: ' . implode('، ', $result['skipped']);
        }

        return back()->with('success', $message);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'إدارة الوحدات متاحة للإدارة العليا فقط.'
        );
    }
}
