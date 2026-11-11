<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `permission:records.view_all,reports.generate` passes when the user holds any listed permission.
 */
class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        $allowed = $user && collect($permissions)->contains(fn (string $permission) => $user->hasPermission(Permission::from($permission)));

        abort_unless($allowed, 403);

        return $next($request);
    }
}
