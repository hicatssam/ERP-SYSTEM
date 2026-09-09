<?php

namespace App\Http\Middleware;

use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        private readonly ModuleService $modules
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $moduleCode
    ): Response {
        if (! $this->modules->isEnabled($moduleCode)) {
            abort(403, 'هذه الوحدة غير مفعلة في النظام.');
        }

        return $next($request);
    }
}
