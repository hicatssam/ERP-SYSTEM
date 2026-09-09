<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordController extends Controller
{
    public function show()
    {
        return view('auth.change-password');
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        // If not must_change_password, require current password
        $rules = [
            'password' => [
                'required', 'confirmed',
                Password::min(10)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ];

        if (! $user->must_change_password) {
            $rules['current_password'] = ['required', 'string'];
        }

        $request->validate($rules);

        if (! $user->must_change_password) {
            if (! Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
            }
        }

        // Ensure new password is different
        if (Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'يجب أن تكون كلمة المرور الجديدة مختلفة عن الحالية.']);
        }

        $user->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        ActivityLog::create([
            'user_id'    => $user->id,
            'action'     => 'password_changed',
            'module'     => 'auth',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        if ($user->must_change_password) {
            // This state is already updated above; redirect to dashboard
        }

    
        return redirect()->route('dashboard')->with('success', 'تم تغيير كلمة المرور بنجاح.');
    }

    public function updateFromProfile(Request $request)
    {
        return $this->update($request);
    }
}
