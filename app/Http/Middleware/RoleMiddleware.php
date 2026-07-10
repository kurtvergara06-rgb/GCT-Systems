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
        string ...$allowedAccess
    ): Response {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (strcasecmp(trim((string) $user->status), 'Active') !== 0) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Your account is not active. Please contact the system administrator.'
                );
        }

        $department = strtolower(trim((string) $user->department));
        $role = strtolower(trim((string) $user->role));

        $department = str_replace(['_', '-'], ' ', $department);
        $role = str_replace(['_', '-'], ' ', $role);

        /*
        |--------------------------------------------------------------------------
        | System Administrator
        |--------------------------------------------------------------------------
        | Admin Head can access all modules.
        */
        $isSystemAdmin =
            ($department === 'admin' && $role === 'head')
            || $role === 'system admin';

        if ($isSystemAdmin) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Department and Role Authorization
        |--------------------------------------------------------------------------
        |
        | Examples:
        | role:operation:head
        | role:operation:head,operation:staff
        | role:warehouse:head,warehouse:staff
        |
        */
        foreach ($allowedAccess as $access) {
            $access = strtolower(trim($access));

            if ($access === '') {
                continue;
            }

            $parts = explode(':', $access, 2);

            /*
            |--------------------------------------------------------------------------
            | Backward-compatible role-only check
            |--------------------------------------------------------------------------
            | Example: role:head
            */
            if (count($parts) === 1) {
                if ($role === $parts[0]) {
                    return $next($request);
                }

                continue;
            }

            [$allowedDepartment, $allowedRole] = $parts;

            $allowedDepartment = str_replace(
                ['_', '-'],
                ' ',
                trim($allowedDepartment)
            );

            $allowedRole = str_replace(
                ['_', '-'],
                ' ',
                trim($allowedRole)
            );

            if (
                $department === $allowedDepartment
                && $role === $allowedRole
            ) {
                return $next($request);
            }
        }

        abort(
            403,
            'You are not authorized to access this department module.'
        );
    }
}