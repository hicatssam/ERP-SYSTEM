<?php

namespace App\Http\Middleware;

use App\Services\AttendanceFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceEnabled
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
            $this->features->attendanceEnabled(),
            403,
            'نظام الحضور والدوام غير مفعّل من إعدادات النظام.'
        );

        return $next($request);
    }
}
