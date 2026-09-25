<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->must_change_password || $user->onboarding_completed) {
            return $next($request);
        }

        $allowedRoutes = [
            'onboarding.show',
            'onboarding.profile.update',
            'onboarding.complete',
            'onboarding.skip',
            'logout',
        ];

        if (in_array($request->route()?->getName(), $allowedRoutes, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Complete your GCT welcome setup before continuing.',
                'onboarding_required' => true,
            ], 423);
        }

        return redirect()->route('onboarding.show');
    }
}
