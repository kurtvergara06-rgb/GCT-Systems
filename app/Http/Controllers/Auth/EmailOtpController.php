<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\EmailDeliveryException;
use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use App\Services\EmailOtpService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EmailOtpController extends Controller
{
    private function applicant(Request $request): User
    {
        $id = $request->session()->get('registration_otp_user_id');
        $user = $id ? User::query()->find($id) : null;

        abort_unless($user && $user->status === 'Pending' && ! $user->hasVerifiedEmail(), 403);

        return $user;
    }

    public function show(Request $request): Response
    {
        $user = $this->applicant($request);

        return response()->view('Login.email-otp', ['otpEmail' => $user->email])
            ->header('Cache-Control', 'no-store');
    }

    public function verify(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $user = $this->applicant($request);
        $input = $request->validate(['code' => ['required', 'regex:/^[0-9]{6}$/']]);

        if (! $otp->verify($user, $input['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid, expired or locked verification code. Request a new code if needed.']);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $request->session()->put('pending_registration_user_id', $user->id);
        $request->session()->forget('registration_otp_user_id');

        return redirect()->route('registration.pending')->with('pending_verified', true);
    }

    public function resend(Request $request, EmailOtpService $otp): RedirectResponse
    {
        $user = $this->applicant($request);

        try {
            $otp->send($user, true);
        } catch (EmailDeliveryException $exception) {
            Log::warning('Employee verification mail could not be resent.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('verification.otp')
                ->with('error', 'The verification code could not be sent. Please try again.');
        }

        return redirect()->route('verification.otp')->with('status', 'A new verification code has been sent.');
    }
}
