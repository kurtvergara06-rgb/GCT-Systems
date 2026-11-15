<?php

namespace App\Services;

use App\Exceptions\EmailDeliveryException;
use App\Models\Admin\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailOtpService
{
    public function send(User $user, bool $resend = false): void
    {
        if ($user->status !== 'Pending' || $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['code' => 'Verification is no longer available.']);
        }

        try {
            Cache::lock("email-otp-send:{$user->id}", 20)->block(3, function () use ($user, $resend): void {
                $this->deliverAndStore($user, $resend);
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'code' => 'A verification email is already being sent. Please wait a moment.',
            ]);
        }
    }

    private function deliverAndStore(User $user, bool $resend): void
    {
        $record = DB::table('email_verification_otps')->where('user_id', $user->id)->first();

        if ($resend && $record && now()->lessThan($record->resend_available_at)) {
            throw ValidationException::withMessages(['code' => 'Please wait 60 seconds before requesting another code.']);
        }

        $code = (string) random_int(100000, 999999);

        try {
            Mail::send(
                'emails.registration-otp',
                ['code' => $code],
                static fn ($mail) => $mail->to($user->email)->subject('GCT-Systems email verification code')
            );
        } catch (Throwable $exception) {
            throw new EmailDeliveryException(
                'The verification email could not be delivered.',
                0,
                $exception,
            );
        }

        DB::transaction(function () use ($user, $code) {
            $record = DB::table('email_verification_otps')->where('user_id', $user->id)->lockForUpdate()->first();

            DB::table('email_verification_otps')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(5),
                    'resend_available_at' => now()->addSeconds(60),
                    'failed_attempts' => 0,
                    'updated_at' => now(),
                    'created_at' => $record?->created_at ?? now(),
                ]
            );
        });
    }

    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $record = DB::table('email_verification_otps')->where('user_id', $user->id)->lockForUpdate()->first();

            if (! $record || $user->status !== 'Pending' || $user->hasVerifiedEmail()) {
                return false;
            }

            if (now()->greaterThanOrEqualTo($record->expires_at) || $record->failed_attempts >= 5) {
                return false;
            }

            if (! Hash::check($code, $record->code_hash)) {
                DB::table('email_verification_otps')->where('user_id', $user->id)
                    ->update(['failed_attempts' => $record->failed_attempts + 1, 'updated_at' => now()]);

                return false;
            }

            DB::table('email_verification_otps')->where('user_id', $user->id)->delete();

            return true;
        });
    }
}
