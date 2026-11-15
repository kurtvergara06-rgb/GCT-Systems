<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountEmailChangeController extends Controller
{
    public function verify(Request $request, int $id, string $token): RedirectResponse
    {
        $changed = DB::transaction(function () use ($id, $token) {
            $pending = DB::table('account_email_changes')->where('user_id', $id)->lockForUpdate()->first();
            if (! $pending || now()->greaterThanOrEqualTo($pending->expires_at)
                || ! hash_equals($pending->token_hash, hash('sha256', $token))) {
                return false;
            }

            $user = User::query()->find($id);
            if (! $user || User::query()->where('email', $pending->new_email)->whereKeyNot($id)->exists()) {
                return false;
            }

            $old = $user->email;
            $user->forceFill(['email' => $pending->new_email, 'email_verified_at' => now()])->save();
            DB::table('account_email_changes')->where('user_id', $id)->delete();

            // Record the completed change without exposing the raw verification token.
            app(ActivityLogService::class)->record(
                $user, request(), 'Verified updated account email', 'Security', 'Admin',
                (string) $user->id, 'Employee account email address changed after mailbox verification.'
            );
            return true;
        });

        return redirect()->route('account.email-change.result')->with(
            'email_change_result',
            $changed ? 'success' : 'invalid'
        );
    }

    public function result(Request $request): \Illuminate\Http\Response|RedirectResponse
    {
        $result = $request->session()->get('email_change_result');

        if (! in_array($result, ['success', 'invalid'], true)) {
            return redirect()->route('login');
        }

        return response()->view('Login.email-change-result', [
            'successful' => $result === 'success',
        ])->header('Cache-Control', 'no-store, private');
    }
}
