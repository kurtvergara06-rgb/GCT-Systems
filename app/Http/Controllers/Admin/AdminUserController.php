<?php

namespace App\Http\Controllers\Admin;

use App\Events\SystemDataUpdated;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Admin\RolePermission;
use App\Models\Admin\User;
use App\Services\RolePermissionService;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    private array $departments = [
        'Maintenance',
        'Warehouse',
        'Purchase',
        'Operation',
    ];

    private array $roles = [
        'head' => 'Head',
        'staff' => 'Staff',
    ];

    private array $statuses = [
        'Active',
        'Inactive',
        'Pending',
        'Rejected',
    ];

    private function isProtectedSystemAdmin(User $user): bool
    {
        return strtolower(trim((string) $user->department)) === 'admin'
            && strtolower(trim((string) $user->role)) === 'head';
    }

    public function index(Request $request)
    {
        $applicationsPage = $request->routeIs('admin.applications');
        $query = User::query();

        // Separate pending registrations from active account administration.
        if ($applicationsPage) {
            $query->where('status', $request->query('status') === 'Rejected' ? 'Rejected' : 'Pending');
        } else {
            $query->whereNotIn('status', ['Pending', 'Rejected']);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $numericId = preg_replace('/[^0-9]/', '', $search);

            $query->where(function ($q) use ($search, $numericId) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");

                if ($numericId !== '') {
                    $q->orWhere('id', (int) $numericId);
                }
            });
        }

        if ($request->filled('department') && $request->department !== 'All Departments') {
            $query->where('department', $request->department);
        }

        if ($request->filled('role') && $request->role !== 'All Roles') {
            $query->where('role', $request->role);
        }

        if (! $applicationsPage && $request->filled('status') && $request->status !== 'All Status') {
            $query->where('status', $request->status);
        }

        TableSort::apply($query, $request, ['created_at' => 'created_at', 'name' => 'name', 'email' => 'email', 'department' => 'department', 'role' => 'role', 'status' => 'status'], 'created_at', 'desc');
        $users = $query->paginate(10)->withQueryString();

        $totalUsers = User::count();
        $activeUsers = User::where('status', 'Active')->count();
        $inactiveUsers = User::where('status', 'Inactive')->count();
        $pendingUsers = User::where('status', 'Pending')->count();
        $rejectedUsers = User::where('status', 'Rejected')->count();
        $verifiedPendingUsers = User::where('status', 'Pending')->whereNotNull('email_verified_at')->count();
        $unverifiedPendingUsers = $pendingUsers - $verifiedPendingUsers;

        $departments = $this->departments;
        $departmentOptions = $this->departments;
        $roles = $this->roles;
        $statuses = $this->statuses;

        return view($applicationsPage
            ? 'Admin.User_Management.applications'
            : 'Admin.User_Management.users', compact(
            'users', 'totalUsers', 'activeUsers', 'inactiveUsers', 'pendingUsers',
            'departments', 'departmentOptions', 'roles', 'statuses',
            'rejectedUsers', 'verifiedPendingUsers', 'unverifiedPendingUsers'
        ));
    }

    public function store(Request $request)
    {
        if ($request->boolean('_permission_update')) {
            return $this->updateRolePermissions($request);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'department' => ['required', 'string', Rule::in($this->departments)],
            'role' => ['required', 'string', Rule::in(array_keys($this->roles))],
            'status' => ['required', 'string', Rule::in($this->statuses)],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
            'onboarding_completed' => false,
            'onboarding_completed_at' => null,
            'department' => $validated['department'],
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.users')
            ->with('success', 'User account created successfully. The user must change the temporary password and complete the welcome setup at first login.');
    }

    private function updateRolePermissions(Request $request)
    {
        $permissionService = app(RolePermissionService::class);

        $validated = $request->validate([
            'role_key' => ['required', 'string', 'exists:role_permissions,role_key'],
            'permissions' => ['required', 'array'],
        ]);

        $rolePermission = RolePermission::where('role_key', $validated['role_key'])->firstOrFail();

        if ($rolePermission->role_key === 'admin_head') {
            return redirect()
                ->route('admin.roles-permissions')
                ->with('error', 'System Admin permissions are protected and always have full system access.');
        }

        if ($permissionService->roleModuleKey($rolePermission) === null) {
            return redirect()
                ->route('admin.roles-permissions', ['role' => $rolePermission->role_key])
                ->with('error', 'This role is not mapped to a valid department module.');
        }

        $normalized = $permissionService->isolatedPermissions(
            $rolePermission,
            $validated['permissions']
        );

        $rolePermission->update([
            'permissions' => $normalized,
            'updated_by' => Auth::id(),
        ]);

        $roleModuleKey = $permissionService->roleModuleKey($rolePermission);

        event(new SystemDataUpdated(
            ucfirst((string) $roleModuleKey),
            'RolePermission',
            'permissions_updated',
            $rolePermission->role_key,
            $rolePermission->label.' permissions were updated.'
        ));

        return redirect()
            ->route('admin.roles-permissions', ['role' => $rolePermission->role_key])
            ->with('success', $rolePermission->label.' permissions updated successfully.');
    }

    public function update(Request $request, User $user)
    {
        if ($this->isProtectedSystemAdmin($user)) {
            return redirect()->route('admin.users')->with('error', 'The protected System Admin account cannot be edited from Account Management.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'department' => ['required', 'string', Rule::in($this->departments)],
            'role' => ['required', 'string', Rule::in(array_keys($this->roles))],
            'status' => ['required', 'string', Rule::in($this->statuses)],
        ]);

        if ($user->status === 'Pending' && $validated['status'] !== 'Pending') {
            abort_unless($request->user()?->hasSystemPermission('administration', 'full_control'), 403);
        }

        if (Auth::id() === $user->id && $validated['status'] !== 'Active') {
            return redirect()->route('admin.users')->with('error', 'You cannot deactivate or place your own account in Pending status.');
        }

        if ($user->status === 'Rejected' && $validated['status'] === 'Active') {
            return redirect()->route('admin.users')->with('error', 'Rejected applications cannot be activated directly.');
        }
        if ($user->status === 'Pending' && $validated['status'] === 'Active') {
            abort_unless($request->user()?->hasSystemPermission('administration', 'full_control'), 403);
        }
        if ($user->status === 'Pending' && $validated['status'] === 'Active'
            && ! $user->hasVerifiedEmail()) {
            return redirect()->route('admin.users')->with('error', 'Verify the employee email before approving this registration.');
        }

        $newEmail = strtolower(trim($validated['email']));
        $emailChanged = strcasecmp($user->email, $newEmail) !== 0;
        $accessChanged = $user->department !== $validated['department']
            || $user->role !== $validated['role'];

        // Leave the existing verified email intact until its replacement proves ownership.
        DB::transaction(function () use ($user, $request, $validated, $newEmail, $emailChanged, $accessChanged) {
            if ($emailChanged) {
                $token = Str::random(64);
                $verifyUrl = URL::temporarySignedRoute(
                    'account.email-change.verify',
                    now()->addMinutes(30),
                    ['id' => $user->id, 'token' => $token]
                );

                DB::table('account_email_changes')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'new_email' => $newEmail,
                        'token_hash' => hash('sha256', $token),
                        'expires_at' => now()->addMinutes(30),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                Mail::raw(
                    "An administrator requested an email change for your GCT-Systems account. To confirm this address, open the link within 30 minutes: {$verifyUrl}. If you did not expect this, ignore this email.",
                    static fn ($mail) => $mail->to($newEmail)->subject('Verify your new GCT-Systems email address')
                );
            }

            $oldDepartment = $user->department;
            $oldRole = $user->role;
            $user->update([
                'name' => $validated['name'],
                'department' => $validated['department'],
                'role' => $validated['role'],
                'status' => $validated['status'],
            ]);

            if ($accessChanged) {
                app(ActivityLogService::class)->record(
                    $request->user(), $request, 'Changed employee department or role', 'Security',
                    'Admin', (string) $user->id,
                    sprintf('Account ID %s: department %s to %s; role %s to %s.',
                        $user->id, $oldDepartment, $user->department, $oldRole, $user->role)
                );
            } elseif ($emailChanged) {
                app(ActivityLogService::class)->record(
                    $request->user(), $request, 'Requested employee email change', 'Security',
                    'Admin', (string) $user->id, 'A new email verification message was requested.'
                );
            }
        });

        return redirect()->route('admin.users')->with('success', $emailChanged
            ? 'Account changes saved. The new email is pending verification; the existing email remains active until verified.'
            : 'User account updated successfully.');
    }

    public function updateStatus(Request $request, User $user)
    {
        if ($this->isProtectedSystemAdmin($user)) {
            return redirect()->route('admin.users')->with('error', 'The protected System Admin account must remain Active.');
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in($this->statuses)],
        ]);

        if ($user->status === 'Pending' && $validated['status'] !== 'Pending') {
            abort_unless($request->user()?->hasSystemPermission('administration', 'full_control'), 403);
        }

        if (Auth::id() === $user->id && $validated['status'] !== 'Active') {
            return redirect()->route('admin.users')->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->status === 'Pending' && $validated['status'] === 'Active') {
            abort_unless($request->user()?->hasSystemPermission('administration', 'full_control'), 403);
            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('admin.users')->with('error', 'Verify the employee email before approving this registration.');
            }
            if (! in_array($user->department, $this->departments, true)
                || ! array_key_exists((string) $user->role, $this->roles)) {
                return redirect()->route('admin.users')->with('error', 'Assign a valid department and role before approving this registration.');
            }
        }

        if ($user->status === 'Rejected' && $validated['status'] === 'Active') {
            return redirect()->route('admin.users')->with('error', 'Rejected applications cannot be activated directly.');
        }

        $previousStatus = $user->status;
        $newStatus = $validated['status'];

        $user->update(['status' => $newStatus]);

        $message = match ([$previousStatus, $newStatus]) {
            ['Pending', 'Active'] => 'Account approved and activated successfully.',
            ['Inactive', 'Active'] => 'Account activated successfully.',
            ['Active', 'Inactive'] => 'Account deactivated successfully.',
            default => 'User status updated successfully.',
        };

        return redirect()->route('admin.users')->with('success', $message);
    }


    public function reviewApplication(Request $request, User $user)
    {
        abort_unless($request->user()?->hasSystemPermission('administration', 'full_control'), 403);
        abort_unless($user->status === 'Pending' && ! $this->isProtectedSystemAdmin($user), 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'department' => ['required_if:decision,approve', 'nullable', Rule::in($this->departments)],
            'role' => ['required_if:decision,approve', 'nullable', Rule::in(array_keys($this->roles))],
        ]);

        if ($data['decision'] === 'approve') {
            if (! $user->hasVerifiedEmail()) {
                return back()->with('error', 'This application cannot be approved until the employee verifies their email.');
            }
            $user->update([
                'department' => $data['department'],
                'role' => $data['role'],
                'status' => 'Active',
            ]);

            return redirect()->route('admin.applications', ['status' => 'Pending'])
                ->with('success', 'Employee application approved. Department and role have been assigned.');
        }

        $user->update(['status' => 'Rejected']);

        return redirect()->route('admin.applications', ['status' => 'Pending'])
            ->with('success', 'Employee application rejected. Account access remains blocked.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $actor = $request->user();

        abort_unless($actor && $actor->hasSystemPermission('administration', 'full_control'), 403);

        if ($this->isProtectedSystemAdmin($user)) {
            return redirect()->route('admin.users')->with('error', 'The protected System Admin password must be changed from Security & Password.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
        ]);

        return redirect()
            ->route('admin.users')
            ->with('success', 'Password reset successfully. The user must change the temporary password at next login.');
    }

    public function destroy(User $user)
    {
        if ($this->isProtectedSystemAdmin($user)) {
            return redirect()->route('admin.users')->with('error', 'The protected System Admin account cannot be deleted.');
        }

        if (Auth::id() === $user->id) {
            return redirect()->route('admin.users')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'User deleted successfully.');
    }
}
