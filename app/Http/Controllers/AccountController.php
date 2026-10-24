<?php

namespace App\Http\Controllers;

use App\Models\Admin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $wasTemporary = (bool) $user->must_change_password;

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

        if ($wasTemporary && ! $user->fresh()->onboarding_completed) {
            return redirect()
                ->route('onboarding.show', ['step' => 1])
                ->with('success', 'Password secured. Continue by confirming your profile.');
        }

        return redirect()
            ->route('account.settings')
            ->with('success', 'Password updated successfully.');
    }
}
