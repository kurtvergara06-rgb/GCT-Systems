<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->status !== 'Active') {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with('error', 'Your account is not active. Please contact the system administrator.');
        }

        if ($user->role === 'System Admin') {
            return $next($request);
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}