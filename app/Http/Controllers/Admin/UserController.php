<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantUserSetting;
use App\Models\User;
use App\Models\Employee;
use App\Support\ProfileImage;
use App\Services\Assistant\AssistantAccessService;
use App\Services\Assistant\AssistantPlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function image(User $user)
    {
        $this->ensureAccessible($user);
        $user->loadMissing('employee');
        $path = ProfileImage::pathFor($user);
        abort_unless($path, 404);

        return Storage::disk('public')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index()
    {
        $users = User::query()->with(['employee', 'roles']);
        if (! auth()->user()->isAdmin() && ! auth()->user()->hasRole('General Manager')) {
            $users->whereHas('employee', fn ($query) => $query->accessibleBy(auth()->user()));
        }
        $users = $users->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        return redirect()
            ->route('employees.index')
            ->with('info', 'أنشئ المستخدمين من صفحة الموظفين.');
    }

    public function edit(User $user, AssistantAccessService $assistantAccess)
    {
        $this->ensureAccessible($user);
        $roles = Role::orderBy('name')->get();

        $user->load(['employee', 'roles', 'assistantSetting']);

        $assistantSetting = $user->assistantSetting ?: new AssistantUserSetting([
            'enabled' => true,
            'topic_mode' => 'inherit',
            'allowed_intents' => null,
            'allow_action_suggestions' => false,
            'max_items' => 8,
        ]);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $roles,
            'assistantSetting' => $assistantSetting,
            'assistantTopics' => $assistantAccess->topics(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAccessible($user);
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'profile_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'remove_profile_image' => [
                'nullable',
                'boolean',
            ],
            'assistant_enabled' => [
                'nullable',
                'boolean',
            ],
            'assistant_topic_mode' => [
                'required',
                Rule::in(['inherit', 'custom']),
            ],
            'assistant_allowed_intents' => [
                'nullable',
                'array',
            ],
            'assistant_allowed_intents.*' => [
                'string',
                'distinct',
                Rule::in(AssistantPlanner::INTENTS),
            ],
            'assistant_allow_action_suggestions' => [
                'nullable',
                'boolean',
            ],
            'assistant_max_items' => [
                'required',
                'integer',
                'min:3',
                'max:20',
            ],
        ], [
            'profile_image.image' => 'الملف المختار يجب أن يكون صورة.',
            'profile_image.mimes' => 'صيغة الصورة يجب أن تكون JPG أو JPEG أو PNG أو WEBP.',
            'profile_image.max'   => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت.',
        ]);

        $userData = [
            'username' => $validated['username'],
            'email'    => $validated['email'],
        ];

        if ($request->boolean('remove_profile_image')) {
            $this->deleteProfileImage($user);

            $userData['profile_image'] = null;
        }

        if ($request->hasFile('profile_image')) {
            $this->deleteProfileImage($user);

            $userData['profile_image'] = $request
                ->file('profile_image')
                ->store('users/profile-images', 'public');
        }

        $user->update($userData);

        $topicMode = $request->input('assistant_topic_mode', 'inherit');
        $allowedIntents = $topicMode === 'custom'
            ? array_values(array_intersect(
                $request->input('assistant_allowed_intents', []),
                AssistantPlanner::INTENTS
            ))
            : null;

        $user->assistantSetting()->updateOrCreate([], [
            'enabled' => $request->boolean('assistant_enabled'),
            'topic_mode' => $topicMode,
            'allowed_intents' => $allowedIntents,
            'allow_action_suggestions' => $request->boolean('assistant_allow_action_suggestions'),
            'max_items' => (int) $request->input('assistant_max_items', 8),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'تم تحديث بيانات وصورة المستخدم بنجاح.');
    }

    public function destroy(User $user)
    {
        $this->ensureAccessible($user);
        return back()->with('error', 'لا يمكن حذف حسابات المستخدمين.');
    }

    public function toggleStatus(User $user)
    {
        $this->ensureAccessible($user);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'لا يمكنك تعطيل حسابك الخاص.');
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        return back()->with('success', 'تم تحديث حالة الحساب.');
    }

    public function resetPassword(User $user)
    {
        $this->ensureAccessible($user);
        $temp = Str::random(10) . '!2B';

        $user->update([
            'password'              => Hash::make($temp),
            'must_change_password'  => true,
            'failed_login_attempts' => 0,
            'login_locked_until'    => null,
        ]);

        return back()->with(
            'success',
            "تم إعادة ضبط كلمة المرور. كلمة المرور المؤقتة: {$temp}"
        );
    }

    public function assignRole(Request $request, User $user)
    {
        $this->ensureAccessible($user);
        abort_if(auth()->user()->hasRole('Branch Manager'), 403);
        $request->validate([
            'role' => ['required', 'exists:roles,name'],
        ]);

        $user->syncRoles([$request->role]);

        return back()->with('success', 'تم تحديث الدور.');
    }

    private function deleteProfileImage(User $user): void
    {
        if (
            $user->profile_image &&
            Storage::disk('public')->exists($user->profile_image)
        ) {
            Storage::disk('public')->delete($user->profile_image);
        }
    }

    private function ensureAccessible(User $target): void
    {
        $actor = auth()->user();
        if ($actor->isAdmin() || $actor->hasRole('General Manager')) {
            return;
        }

        abort_unless($target->employee && Employee::query()->accessibleBy($actor)
            ->whereKey($target->employee->id)->exists(), 403);
    }
}
