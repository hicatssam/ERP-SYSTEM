<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    private const GUARD_NAME = 'web';
    private const ADMIN_ROLE = 'Admin';

    public function index()
    {
        $roles = Role::query()
            ->where('guard_name', self::GUARD_NAME)
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = $this->getGroupedPermissions();

        return view('admin.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(
                    config('permission.table_names.roles', 'roles'),
                    'name'
                )->where(function ($query) {
                    return $query->where(
                        'guard_name',
                        self::GUARD_NAME
                    );
                }),
            ],

            'permissions' => [
                'nullable',
                'array',
            ],

            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(
                    config('permission.table_names.permissions', 'permissions'),
                    'name'
                )->where(function ($query) {
                    return $query->where(
                        'guard_name',
                        self::GUARD_NAME
                    );
                }),
            ],
        ]);

        $roleName = trim($validated['name']);

        if (strcasecmp($roleName, self::ADMIN_ROLE) === 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'يوجد دور مدير أساسي بالفعل، ولا يمكن إنشاء دور مدير آخر.',
                ]);
        }

        DB::transaction(function () use ($validated, $roleName) {
            $role = Role::create([
                'name'       => $roleName,
                'guard_name' => self::GUARD_NAME,
            ]);

            $role->syncPermissions(
                array_values(array_unique($validated['permissions'] ?? []))
            );
        });

        $this->clearPermissionCache();

        return redirect()
            ->route('roles.index')
            ->with('success', 'تم إنشاء الدور وتوزيع الصلاحيات بنجاح.');
    }

    public function show(Role $role)
    {
        $this->ensureWebGuard($role);

        $role->load([
            'permissions' => function ($query) {
                $query->orderBy('name');
            },
        ]);

        return view('admin.roles.show', compact('role'));
    }

    public function edit(Role $role)
    {
        $this->ensureWebGuard($role);

        $role->load('permissions');

        $permissions = $this->getGroupedPermissions();

        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->ensureWebGuard($role);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(
                    config('permission.table_names.roles', 'roles'),
                    'name'
                )
                    ->where(function ($query) {
                        return $query->where(
                            'guard_name',
                            self::GUARD_NAME
                        );
                    })
                    ->ignore($role->getKey()),
            ],

            'permissions' => [
                'nullable',
                'array',
            ],

            'permissions.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(
                    config('permission.table_names.permissions', 'permissions'),
                    'name'
                )->where(function ($query) {
                    return $query->where(
                        'guard_name',
                        self::GUARD_NAME
                    );
                }),
            ],
        ]);

        $isAdminRole = $this->isAdminRole($role);

        if (
            !$isAdminRole &&
            strcasecmp(trim($validated['name']), self::ADMIN_ROLE) === 0
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'لا يمكن تحويل هذا الدور إلى دور المدير الأساسي.',
                ]);
        }

        DB::transaction(function () use ($role, $validated, $isAdminRole) {
            if ($isAdminRole) {
                /*
                 * اسم دور المدير لا يتغير.
                 * يحصل المدير على جميع صلاحيات web تلقائيًا.
                 */
                $allPermissions = Permission::query()
                    ->where('guard_name', self::GUARD_NAME)
                    ->pluck('name')
                    ->all();

                $role->syncPermissions($allPermissions);

                return;
            }

            $role->update([
                'name' => trim($validated['name']),
            ]);

            $role->syncPermissions(
                array_values(array_unique($validated['permissions'] ?? []))
            );
        });

        $this->clearPermissionCache();

        return redirect()
            ->route('roles.index')
            ->with(
                'success',
                $isAdminRole
                    ? 'تم تحديث دور المدير ومنحه جميع الصلاحيات.'
                    : 'تم تحديث الدور والصلاحيات بنجاح.'
            );
    }

    public function destroy(Role $role)
    {
        $this->ensureWebGuard($role);

        if ($this->isAdminRole($role)) {
            return back()->with('error', 'لا يمكن حذف دور المدير.');
        }

        if ($role->users()->exists()) {
            return back()->with(
                'error',
                'لا يمكن حذف هذا الدور لأنه مرتبط بمستخدمين. انقل المستخدمين إلى دور آخر أولًا.'
            );
        }

        $role->delete();

        $this->clearPermissionCache();

        return redirect()
            ->route('roles.index')
            ->with('success', 'تم حذف الدور بنجاح.');
    }

    private function getGroupedPermissions()
    {
        return Permission::query()
            ->where('guard_name', self::GUARD_NAME)
            ->orderBy('name')
            ->get()
            ->groupBy(function (Permission $permission) {
                return explode('.', $permission->name)[0];
            });
    }

    private function ensureWebGuard(Role $role): void
    {
        abort_unless(
            $role->guard_name === self::GUARD_NAME,
            404
        );
    }

    private function isAdminRole(Role $role): bool
    {
        return strcasecmp($role->name, self::ADMIN_ROLE) === 0;
    }

    private function clearPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}