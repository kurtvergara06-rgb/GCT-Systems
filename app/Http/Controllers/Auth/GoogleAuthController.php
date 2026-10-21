<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
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

        $request->session()->forget('google_link_pending');
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
                if (strtolower((string) $existing->status) === 'pending') {
                    $request->session()->put('pending_registration_user_id', $existing->id);
                    return redirect()->route('registration.pending');
                }
                return redirect()->route('login')->with('error', 'This account is inactive. Contact the system administrator.');
            }
            Auth::login($existing, true);
            $request->session()->regenerate();
            return redirect()->route('login');
        }

        // Require password ownership proof, not just an email match, before linking.
        $sameEmail = User::where('email', $email)->first();
        if ($sameEmail) {
            if (strtolower((string) $sameEmail->status) === 'pending') {
                $request->session()->put('pending_registration_user_id', $sameEmail->id);
                return redirect()->route('registration.pending');
            }
            if (strtolower((string) $sameEmail->status) !== 'active') {
                return redirect()->route('login')->with('error', 'This account is inactive. Contact the administrator.');
            }
            if ($sameEmail->hasSystemPermission('administration', 'full_control')
                || strtolower((string) $sameEmail->role) === 'system admin'
                || strtolower((string) $sameEmail->department) === 'admin') {
                return redirect()->route('login')->with('error', 'Administrator Google linking requires a separate authorized process.');
            }
            if (filled($sameEmail->google_id)) {
                return redirect()->route('login')->with('error', 'This account is already linked to another Google identity.');
            }
            $request->session()->put('google_link_pending', [
                'user_id' => $sameEmail->id,
                'google_id' => $subject,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);
            return redirect()->route('auth.google.link');
        }

        try {
            $newUser = DB::transaction(function () use ($subject, $email, $profile): User {
                return User::create([
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

        $request->session()->put('pending_registration_user_id', $newUser->id);
        return redirect()->route('registration.pending')->with('pending_verified', true);
    }
    private function pendingLink(Request $request): ?array
    {
        $pending = $request->session()->get('google_link_pending');
        if (! is_array($pending) || ! isset($pending['user_id'], $pending['google_id'], $pending['expires_at'])
            || now()->timestamp >= (int) $pending['expires_at']) {
            $request->session()->forget('google_link_pending');
            return null;
        }
        return $pending;
    }

    public function linkForm(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        $pending = $this->pendingLink($request);
        if (! $pending) {
            return redirect()->route('login')->with('error', 'Google linking session expired. Please try again.');
        }
        return response()->view('Login.google-link', [
            'account' => User::query()->findOrFail($pending['user_id']),
        ])->header('Cache-Control', 'no-store');
    }

    public function confirmLink(Request $request): RedirectResponse
    {
        $pending = $this->pendingLink($request);
        if (! $pending) {
            return redirect()->route('login')->with('error', 'Google linking session expired. Please try again.');
        }
        $request->validate(['password' => ['required', 'string']]);

        $user = User::query()->find($pending['user_id']);
        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Incorrect account password.']);
        }
        $linked = DB::transaction(function () use ($user, $pending): bool {
            $fresh = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (! $fresh || strtolower((string) $fresh->status) !== 'active'
                || filled($fresh->google_id)
                || strtolower((string) $fresh->department) === 'admin'
                || strtolower((string) $fresh->role) === 'system admin'
                || $fresh->hasSystemPermission('administration', 'full_control')
                || User::query()->where('google_id', $pending['google_id'])->exists()) {
                return false;
            }
            $fresh->forceFill(['google_id' => $pending['google_id']])->save();
            return true;
        });
        $request->session()->forget('google_link_pending');
        if (! $linked) {
            return redirect()->route('login')->with('error', 'Google account linking was not permitted.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->route('login');
    }

}
