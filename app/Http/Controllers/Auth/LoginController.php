<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Authenticate the user and redirect them to the correct dashboard.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $remember = $request->boolean('remember');

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($user === null) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'Incorrect email address or password.',
                ]);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'password' => 'Wrong password.',
                ]);
        }

        Auth::login($user, $remember);

        $request->session()->regenerate();

        /** @var User|null $authenticatedUser */
        $authenticatedUser = Auth::user();

        if ($authenticatedUser === null) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Authentication failed. Please try again.');
        }

        /*
        |--------------------------------------------------------------------------
        | Account Status Check
        |--------------------------------------------------------------------------
        */

        $status = strtolower(
            trim((string) ($authenticatedUser->status ?? 'Active'))
        );

        if ($status !== 'active') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with(
                    'error',
                    'Your account is not active. Please contact the system administrator.'
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Update Last Login
        |--------------------------------------------------------------------------
        */

        $authenticatedUser->forceFill([
            'last_login_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Determine Redirect Path
        |--------------------------------------------------------------------------
        */

        $redirectPath = $this->redirectByDepartmentAndRole(
            $authenticatedUser->department ?? null,
            $authenticatedUser->role ?? null
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Old Intended URL
        |--------------------------------------------------------------------------
        |
        | This prevents Laravel from reusing malformed session URLs such as:
        | /https:/admin/dashboard
        |
        */

        $request->session()->forget('url.intended');

        return redirect($redirectPath);
    }

    /**
     * Log the authenticated user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Determine the correct relative landing page based on department and role.
     */
    private function redirectByDepartmentAndRole(
        ?string $department,
        ?string $role
    ): string {
        $department = $this->normalizeValue($department);
        $role = $this->normalizeValue($role);

        /*
        |--------------------------------------------------------------------------
        | Admin Department
        |--------------------------------------------------------------------------
        */

        $adminRoles = [
            'head',
            'staff',
            'admin',
            'system admin',
            'system administrator',
        ];

        if (
            in_array($department, ['admin', 'administration'], true) &&
            in_array($role, $adminRoles, true)
        ) {
            return route('admin.dashboard', [], false);
        }

        /*
        |--------------------------------------------------------------------------
        | Maintenance Department
        |--------------------------------------------------------------------------
        */

        if (
            $department === 'maintenance' &&
            in_array($role, ['head', 'staff'], true)
        ) {
            return route('maintenance-dashboard', [], false);
        }

        /*
        |--------------------------------------------------------------------------
        | Purchase Department
        |--------------------------------------------------------------------------
        */

        if (
            in_array($department, ['purchase', 'purchasing'], true) &&
            in_array($role, ['head', 'staff'], true)
        ) {
            return route('purchase-orders', [], false);
        }

        /*
        |--------------------------------------------------------------------------
        | Warehouse Department
        |--------------------------------------------------------------------------
        */

        if (
            $department === 'warehouse' &&
            in_array($role, ['head', 'staff'], true)
        ) {
            return route('warehouse.dashboard', [], false);
        }

        /*
        |--------------------------------------------------------------------------
        | Operation Department
        |--------------------------------------------------------------------------
        */

        if (
            in_array($department, ['operation', 'operations'], true) &&
            in_array($role, ['head', 'staff'], true)
        ) {
            return route('dashboard-operation', [], false);
        }

        /*
        |--------------------------------------------------------------------------
        | Unsupported Assignment
        |--------------------------------------------------------------------------
        */

        Auth::logout();

        return route('login', [], false);
    }

    /**
     * Normalize department and role values.
     */
    private function normalizeValue(?string $value): string
    {
        $value = strtolower(trim($value ?? ''));

        $value = str_replace(
            ['_', '-'],
            ' ',
            $value
        );

        return preg_replace('/\s+/', ' ', $value) ?? '';
    }
}
