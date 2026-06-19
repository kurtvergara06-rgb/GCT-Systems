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

        return redirect()->intended($this->redirectByRole($user->role, $user->department));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectByRole(?string $role, ?string $department): string
    {
        $normalizedRole = strtolower(trim($role ?? ''));
        $normalizedDepartment = strtolower(trim($department ?? ''));

        if ($normalizedRole === 'system admin' || $normalizedRole === 'admin') {
            return route('admin.dashboard');
        }

        if ($normalizedRole === 'head') {
            return match ($normalizedDepartment) {
                'maintenance' => route('maintenance-dashboard'),
                'purchase', 'purchasing' => route('purchase-orders'),
                'warehouse' => route('inventory'),
                'operation' => route('dashboard-operation'),
                default => route('login'),
            };
        }

        if ($normalizedRole === 'staff') {
            return match ($normalizedDepartment) {
                'maintenance' => route('maintenance-dashboard'),
                'purchase', 'purchasing' => route('purchase-orders'),
                'warehouse' => route('inventory'),
                'operation' => route('dashboard-operation'),
                default => route('login'),
            };
        }

        return match ($role) {
            'System Admin' => route('admin.dashboard'),

            'Maintenance Head',
            'Maintenance Staff' => route('maintenance-dashboard'),

            'Purchasing Head',
            'Purchasing Staff' => route('purchase-orders'),

            'Warehouse Staff' => route('inventory'),

            'Operation Head',
            'Operation Staff' => route('dashboard-operation'),

            default => route('login'),
        };
    }
}