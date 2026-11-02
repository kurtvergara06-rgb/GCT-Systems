<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        // The welcome tutorial is optional; route permissions still apply.
        return $next($request);
    }
}
