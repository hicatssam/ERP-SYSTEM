<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['login'])
                    ->orWhere('email', $credentials['login'])
                    ->first();

        // Account not found
        if (! $user) {
            throw ValidationException::withMessages([
                'login' => [__('auth.failed')],
            ]);
        }

        // Account inactive
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'login' => ['هذا الحساب غير مفعل. يرجى التواصل مع المسؤول.'],
            ]);
        }

        // Locked out
        if ($user->isLocked()) {
            $minutes = now()->diffInMinutes($user->login_locked_until);
            throw ValidationException::withMessages([
                'login' => ["تم قفل الحساب مؤقتاً. يرجى المحاولة بعد {$minutes} دقيقة."],
            ]);
        }

        // Wrong password
        if (! Hash::check($credentials['password'], $user->password)) {
            $user->increment('failed_login_attempts');

            if ($user->failed_login_attempts >= 5) {
                $user->update(['login_locked_until' => now()->addMinutes(15)]);
                $this->logActivity($user, 'login_locked', 'auth');
                throw ValidationException::withMessages([
                    'login' => ['تم قفل الحساب مؤقتاً بسبب محاولات فاشلة متكررة. يرجى المحاولة بعد 15 دقيقة.'],
                ]);
            }

            $this->logActivity($user, 'login_failed', 'auth');
            throw ValidationException::withMessages([
                'login' => [__('auth.failed')],
            ]);
        }

        // Success — reset counters
        $user->update([
            'failed_login_attempts' => 0,
            'login_locked_until'    => null,
            'last_login_at'         => now(),
        ]);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $this->logActivity($user, 'login', 'auth');

        // Force password change on first login
        if ($user->must_change_password) {
            return redirect()->route('auth.change-password')
                ->with('warning', 'يجب عليك تغيير كلمة المرور قبل المتابعة.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $this->logActivity($user, 'logout', 'auth');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function logActivity(?User $user, string $action, string $module): void
    {
        if (! $user) return;
        ActivityLog::create([
            'user_id'    => $user->id,
            'action'     => $action,
            'module'     => $module,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
