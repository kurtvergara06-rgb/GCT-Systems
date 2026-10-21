<?php

namespace App\Services;

use App\Models\Admin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailOtpService
{
    public function send(User $user, bool $resend = false): void
    {
        if ($user->status !== 'Pending' || $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['code' => 'Verification is no longer available.']);
        }

        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $code, $resend) {
            $record = DB::table('email_verification_otps')->where('user_id', $user->id)->lockForUpdate()->first();

            if ($resend && $record && now()->lessThan($record->resend_available_at)) {
                throw ValidationException::withMessages(['code' => 'Please wait 60 seconds before requesting another code.']);
            }

            // Send before replacing the previous valid code: failed mail leaves it usable.
            Mail::send(
                'emails.registration-otp',
                ['code' => $code],
                static fn ($mail) => $mail->to($user->email)->subject('GCT-Systems email verification code')
            );

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
