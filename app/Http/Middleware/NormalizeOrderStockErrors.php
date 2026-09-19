<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class NormalizeOrderStockErrors
{
    /**
     * Make inventory/recipe validation failures raised while confirming an
     * order land in one predictable validation bag key: `stock`.
     *
     * The order show page already renders `stock` errors inside the branded
     * inventory modal. Some inventory services historically returned the same
     * failures under `items`, which meant Laravel redirected back correctly but
     * the popup never opened. This middleware keeps the domain service reusable
     * while normalizing only the order-confirm flow.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ValidationException $exception) {
            if (! $request->routeIs('orders.confirm')) {
                throw $exception;
            }

            $errors = $exception->errors();

            $stockErrors = collect([
                ...($errors['stock'] ?? []),
                ...($errors['items'] ?? []),
            ])
                ->map(fn ($message) => trim((string) $message))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($stockErrors === []) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'stock' => $stockErrors,
            ]);
        }
    }
}
