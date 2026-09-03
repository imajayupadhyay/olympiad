<?php

namespace App\Http\Middleware;

use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $module, string $action = 'read'): Response
    {
        if (AdminPermissions::allows($request->user(), $module, $action)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'You do not have permission to perform this action.');
        }

        $fallback = AdminPermissions::firstAllowedRoute($request->user());

        if ($fallback && $fallback !== $request->fullUrl()) {
            return redirect($fallback)->with('error', 'You do not have permission to access that admin area.');
        }

        abort(403, 'You do not have permission to access that admin area.');
    }
}
