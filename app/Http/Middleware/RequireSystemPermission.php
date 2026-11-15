<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSystemPermission
{
    public function handle(
        Request $request,
        Closure $next,
        string $module,
        string $capability
    ): Response {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $department = strtolower(trim((string) $user->department));
        $role = strtolower(trim((string) $user->role));
        $isSystemAdmin = ($department === 'admin' && $role === 'head')
            || $role === 'system admin';

        abort_unless(
            $isSystemAdmin || $user->hasSystemPermission($module, $capability),
            403,
            "Your role does not have permission to {$capability} {$module} records."
        );

        return $next($request);
    }
}
