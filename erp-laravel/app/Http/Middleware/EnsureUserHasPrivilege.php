<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasPrivilege
{
    public function handle(Request $request, Closure $next, string $privilege)
    {
        if (! $request->user()?->hasPrivilege($privilege)) {
            abort(403, 'Anda tidak memiliki privilege untuk akses ini.');
        }

        return $next($request);
    }
}
