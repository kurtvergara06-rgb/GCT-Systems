<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->with('error', 'Google sign-in is not configured yet.');
        }

        $state = Str::random(64);
        $verifier = Str::random(96);
        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_verifier', $verifier);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('google_oauth_state');
        $verifier = $request->session()->pull('google_oauth_verifier');

        if (! $this->configured() || ! is_string($expected) || ! is_string($verifier)
            || ! is_string($request->query('state'))
            || ! hash_equals($expected, $request->query('state'))
            || ! is_string($request->query('code'))) {
            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }

        try {
            $tokenResponse = Http::asForm()->timeout(12)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
                'code_verifier' => $verifier,
            ])->throw();

            $accessToken = $tokenResponse->json('access_token');
            if (! is_string($accessToken) || $accessToken === '') {
                throw new \RuntimeException('Google returned no access token.');
            }
            $profile = Http::withToken($accessToken)->timeout(12)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth exchange failed.', ['exception' => $exception->getMessage()]);
            return redirect()->route('login')->with('error', 'Google authentication is temporarily unavailable.');
        }

        $subject = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        if (! is_string($subject) || $subject === '' || ! is_string($email)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($profile['email_verified'] ?? false) !== true) {
            return redirect()->route('login')->with('error', 'A verified Google email is required.');
        }

        $existing = User::where('google_id', $subject)->first();
        if ($existing) {
            if (strtolower((string) $existing->status) !== 'active') {
                return redirect()->route('login')->with('status', 'Your account is pending approval or inactive. Contact the system administrator.');
            }
            Auth::login($existing, true);
            $request->session()->regenerate();
            return redirect()->route('login');
        }

        // Never link an existing privileged account using email alone.
        if (User::where('email', $email)->exists()) {
            return redirect()->route('login')->with('error', 'An account with this email already exists. Sign in using your current method and contact the administrator about Google linking.');
        }

        try {
            DB::transaction(function () use ($subject, $email, $profile): void {
                User::create([
                    'name' => Str::limit(trim((string) ($profile['name'] ?? 'Google Employee')), 255, ''),
                    'email' => strtolower($email),
                    'google_id' => $subject,
                    'email_verified_at' => now(),
                    'password' => Str::random(64),
                    'department' => 'Unassigned',
                    'role' => 'unassigned',
                    'status' => 'Pending',
                    'must_change_password' => false,
                    'onboarding_completed' => false,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::warning('Google account registration failed.', ['exception' => $exception->getMessage()]);
            return redirect()->route('login')->with('error', 'Unable to complete Google registration. Please contact the administrator.');
        }

        return redirect()->route('login')->with('status', 'Google email verified. Registration is pending administrator approval.');
    }
}
