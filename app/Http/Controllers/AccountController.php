<?php

namespace App\Http\Controllers;

use App\Models\Admin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function profile(Request $request): View
    {
        return view('Account.profile', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        return redirect()
            ->route('account.profile')
            ->with('success', 'Profile updated successfully.');
    }

    public function updateProfilePhoto(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // PHP may reject an upload before Laravel can inspect its image contents.
        // Report the underlying upload condition rather than a generic validation error.
        $avatar = $request->file('avatar');
        if ($avatar && ! $avatar->isValid()) {
            $message = match ($avatar->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The profile photo exceeds the server upload limit (2 MB). Choose a smaller image.',
                UPLOAD_ERR_PARTIAL => 'The profile photo was only partially uploaded. Please try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is unavailable. Contact your administrator.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded photo. Contact your administrator.',
                UPLOAD_ERR_EXTENSION => 'A server extension blocked the photo upload. Contact your administrator.',
                default => 'The profile photo upload failed. Please try again.',
            };

            return back()->withErrors(['avatar' => $message]);
        }

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $validated['avatar']->store('profile-photos', 'public');

        if (! $path) {
            return back()->withErrors([
                'avatar' => 'The profile photo could not be saved. Please try again.',
            ]);
        }

        $previousPath = $user->avatar_path;
        $user->update(['avatar_path' => $path]);

        if ($previousPath && $previousPath !== $path) {
            Storage::disk('public')->delete($previousPath);
        }

        return redirect()
            ->route('account.profile')
            ->with('success', 'Profile photo updated successfully.');
    }

    public function destroyProfilePhoto(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $previousPath = $user->avatar_path;

        $user->update(['avatar_path' => null]);

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return redirect()
            ->route('account.profile')
            ->with('success', 'Profile photo removed successfully.');
    }

    public function settings(Request $request): View
    {
        return view('Account.settings', [
            'user' => $request->user(),
        ]);
    }

    public function personalization(Request $request): View
    {
        return view('Account.personalization', [
            'user' => $request->user(),
        ]);
    }

    public function preferences(Request $request): View
    {
        $sessionTable = (string) config('session.table', 'sessions');
        $sessionsSupported = config('session.driver') === 'database'
            && Schema::hasTable($sessionTable)
            && Schema::hasColumns($sessionTable, ['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);

        $sessions = $sessionsSupported
            ? DB::table($sessionTable)
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->map(fn ($session) => (object) [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address ?: 'Unknown network',
                    'device' => str($session->user_agent ?: 'Unknown browser')->limit(110)->toString(),
                    'last_activity' => \Carbon\Carbon::createFromTimestamp((int) $session->last_activity)->timezone(config('app.timezone', 'UTC'))->format('M d, Y h:i A'),
                    'current' => hash_equals((string) $request->session()->getId(), (string) $session->id),
                ])
            : collect();

        return view('Account.preferences', [
            'user' => $request->user(),
            'sessions' => $sessions,
            'sessionsSupported' => $sessionsSupported,
        ]);
    }

    public function revokeSession(Request $request, string $sessionId): RedirectResponse
    {
        $sessionTable = (string) config('session.table', 'sessions');
        abort_unless(
            config('session.driver') === 'database'
                && Schema::hasTable($sessionTable)
                && Schema::hasColumns($sessionTable, ['id', 'user_id']),
            404
        );

        // Current-session logout is handled through the existing logout
        // route; this endpoint can revoke only the owner's other sessions.
        abort_if(hash_equals((string) $request->session()->getId(), $sessionId), 422);

        $deleted = DB::table($sessionTable)
            ->where('user_id', $request->user()->id)
            ->where('id', $sessionId)
            ->delete();

        abort_unless($deleted === 1, 404);

        return redirect()
            ->route('account.preferences')
            ->with('success', 'The selected session was signed out.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()
                ->withErrors([
                    'current_password' => 'The current password is incorrect.',
                ])
                ->onlyInput('current_password');
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);

        return redirect()
            ->route('account.settings')
            ->with('success', 'Password updated successfully.');
    }
}
