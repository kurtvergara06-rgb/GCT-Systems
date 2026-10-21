<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use App\Services\EmailOtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class RegistrationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('login');
        }

        return response()->view('Login.register')->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = DB::transaction(fn () => User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'department' => 'Unassigned',
            'role' => 'unassigned',
            'status' => 'Pending',
            'must_change_password' => false,
            'onboarding_completed' => false,
            'onboarding_completed_at' => null,
        ]));

        try {
            $otp->send($user);
        } catch (\Throwable $exception) {
            Log::warning('Employee verification mail could not be delivered.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $request->session()->put('registration_otp_user_id', $user->id);

        return redirect()->route('verification.otp');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('registration.pending')->with('pending_verified', true);
    }
}
