<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->role !== 'admin') {
            throw new AccessDeniedHttpException('Forbidden.');
        }

        return $next($request);
    }
}
