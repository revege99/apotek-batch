<?php

namespace App\Http\Middleware;

use App\Support\NavigationAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasMenuAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            NavigationAccess::canAccessRoute($request->user(), optional($request->route())->getName()),
            403
        );

        return $next($request);
    }
}
