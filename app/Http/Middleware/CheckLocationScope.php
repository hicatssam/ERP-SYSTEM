<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLocationScope
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('auth.login');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $primaryLocation = $user->primaryLocation();

        abort_unless(
            $primaryLocation,
            403,
            'لا يوجد فرع رئيسي مرتبط بحسابك.'
        );

        $request->attributes->set(
            'user_primary_location',
            $primaryLocation
        );

        return $next($request);
    }
}