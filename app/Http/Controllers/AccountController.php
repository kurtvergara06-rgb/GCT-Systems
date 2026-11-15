<?php

namespace App\Http\Controllers;

use App\Models\Admin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('account.settings')
            ->with('success', 'Password updated successfully.');
    }
}
