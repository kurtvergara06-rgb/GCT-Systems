<?php

namespace App\Listeners;

use App\Events\SystemDataUpdated;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateUserLastLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        $user->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();

        Log::info('User last login updated', [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
        ]);

        try {
            event(new SystemDataUpdated(
                'Admin',
                'User',
                'login',
                $user->id,
                $user->name . ' logged in.'
            ));

            Log::info('User login Reverb event dispatched', [
                'user_id' => $user->id,
            ]);
        } catch (Throwable $exception) {
            Log::error('User login Reverb broadcast failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}