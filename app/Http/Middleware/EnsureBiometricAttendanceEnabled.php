<?php

namespace App\Http\Middleware;

use App\Services\AttendanceFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBiometricAttendanceEnabled
{
    public function __construct(
        private readonly AttendanceFeatureService $features
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        abort_unless(
            $this->features->biometricEnabled(),
            403,
            'الحضور البيومتري (البصمة/الوجه) غير مفعّل من إعدادات النظام.'
        );

        return $next($request);
    }
}
