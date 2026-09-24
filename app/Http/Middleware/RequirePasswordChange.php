<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $allowedRoutes = [
            'account.settings',
            'account.password.update',
            'logout',
        ];

        if (in_array($request->route()?->getName(), $allowedRoutes, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You must change your temporary password before continuing.',
                'must_change_password' => true,
            ], 423);
        }

        return redirect()
            ->route('account.settings')
            ->with('password_change_required', true);
    }
}
