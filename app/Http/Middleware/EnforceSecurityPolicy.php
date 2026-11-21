<?php
namespace App\Http\Middleware;

use App\Services\SecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) return $next($request);
        $policy = SecurityPolicy::current();
        $timeout = (int) $policy['session_timeout'];
        if ($timeout > 0) {
            $now = now()->timestamp;
            $last = (int) $request->session()->get('gct_security_last_activity', $now);
            if ($now - $last > $timeout * 60) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return $request->expectsJson()
                    ? response()->json(['message' => 'Session expired due to inactivity.'], 401)
                    : redirect()->route('login')->with('error', 'Your session expired due to inactivity.');
            }
            $request->session()->put('gct_security_last_activity', $now);
        } else {
            $request->session()->forget('gct_security_last_activity');
        }
        $expiry = (int) $policy['password_expiration'];
        if ($expiry > 0 && $user->password_changed_at
            && $user->password_changed_at->lte(now()->subDays($expiry))
            && ! $user->must_change_password) {
            $user->forceFill(['must_change_password' => true])->save();
        }
        return $next($request);
    }
}
