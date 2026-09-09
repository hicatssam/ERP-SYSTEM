<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->must_change_password) {
            if (! $request->routeIs('auth.change-password', 'auth.logout')) {
                return redirect()->route('auth.change-password')
                    ->with('warning', 'يجب عليك تغيير كلمة المرور قبل المتابعة.');
            }
        }

        return $next($request);
    }
}
