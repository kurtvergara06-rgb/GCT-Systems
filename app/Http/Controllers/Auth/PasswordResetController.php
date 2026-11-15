<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function requestForm(): Response
    {
        return response()->view('Login.forgot-password')->header('Cache-Control', 'no-store');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        // Always return a generic response to avoid revealing registered emails.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If an account exists for this email, a password reset link will be sent.');
    }

    public function resetForm(string $token, Request $request): Response
    {
        return response()->view('Login.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ])->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password changed successfully. You can now sign in.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
