<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Invalid email or password.');
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (($user->status ?? 'Active') !== 'Active') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your account is not active. Please contact the system administrator.');
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        return redirect()->intended($this->redirectByDepartmentAndRole($user->department, $user->role));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectByDepartmentAndRole(?string $department, ?string $role): string
    {
        $department = strtolower(trim($department ?? ''));
        $role = strtolower(trim($role ?? ''));

        $department = str_replace(['_', '-'], ' ', $department);
        $role = str_replace(['_', '-'], ' ', $role);

        /*
          Admin role is removed.
          Admin account should be:
          department = Admin
          role = head
        */
        if ($department === 'admin' && in_array($role, ['head', 'staff'], true)) {
            return route('admin.dashboard');
        }

        if ($department === 'maintenance' && in_array($role, ['head', 'staff'], true)) {
            return route('maintenance-dashboard');
        }

        if (in_array($department, ['purchase', 'purchasing'], true) && in_array($role, ['head', 'staff'], true)) {
            return route('purchase-orders');
        }

        if ($department === 'warehouse' && in_array($role, ['head', 'staff'], true)) {
            return route('inventory');
        }

        if ($department === 'operation' && in_array($role, ['head', 'staff'], true)) {
            return route('dashboard-operation');
        }

        return route('login');
    }
}