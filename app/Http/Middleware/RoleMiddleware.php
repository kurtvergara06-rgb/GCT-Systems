<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->status !== 'Active') {
            auth()->logout();

            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Your account is not active. Please contact the system administrator.'
                );
        }

        $department = strtolower(trim($user->department ?? ''));
        $role = strtolower(trim($user->role ?? ''));

        /*
        |--------------------------------------------------------------------------
        | System Admin Access
        |--------------------------------------------------------------------------
        | An Admin department user with role "head" is your System Admin.
        */
        $isSystemAdmin =
            ($department === 'admin' && $role === 'head')
            || $role === 'system admin';

        if ($isSystemAdmin) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Standard Department Roles
        |--------------------------------------------------------------------------
        */
        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}