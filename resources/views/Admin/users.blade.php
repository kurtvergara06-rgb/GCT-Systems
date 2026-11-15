<x-layout.app
    title="FROMS - User Management"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/users.css',
        'resources/js/Admin/users.js'
    ]"
>
    @php
        $authUser = auth()->user();

        $sidebarName = $authUser?->name ?? 'System Admin';

        $sidebarDepartment = trim($authUser?->department ?? 'Admin');
        $sidebarRoleValue = strtolower(trim($authUser?->role ?? 'head'));

        if (strtolower($sidebarDepartment) === 'admin') {
            $sidebarRole = $sidebarRoleValue === 'head'
                ? 'System Admin'
                : 'Admin Staff';
        } else {
            $sidebarRole = $sidebarDepartment . ' ' . ucfirst($sidebarRoleValue ?: 'staff');
        }

        /*
        |--------------------------------------------------------------------------
        | Department Options
        |--------------------------------------------------------------------------
        | Controller sends $departments. This creates $departmentOptions safely
        | for the filter and Add User modal.
        */
        $departmentOptions = $departments ?? [
            'Maintenance',
            'Warehouse',
            'Purchase',
            'Operation',
        ];

        /*
        |--------------------------------------------------------------------------
        | Role Display Formatter
        |--------------------------------------------------------------------------
        */
        $formatRole = function ($user) {
            $department = trim($user->department ?? '');
            $role = strtolower(trim($user->role ?? ''));

            if (strtolower($department) === 'admin') {
                return $role === 'head'
                    ? 'System Admin'
                    : 'Admin Staff';
            }

            if ($department === '') {
                return ucfirst($role ?: 'User');
            }

            return $department . ' ' . ucfirst($role ?: 'Staff');
        };
    @endphp

    <div class="app admin-users-app">

        <x-layout.sidebar
            department="Admin"
            subtitle="System Management"
            icon="fa-shield-halved"
            :user-name="$sidebarName"
            :user-role="$sidebarRole"
            :items="[
                [
                    'label' => 'Dashboard',
                    'route' => 'admin.dashboard',
                    'icon' => 'fa-table-cells-large'
                ],
                [
                    'label' => 'User Management',
                    'route' => 'admin.users',
                    'icon' => 'fa-users-gear'
                ],
                [
                    'label' => 'Permissions',
                    'route' => 'admin.permissions',
                    'icon' => 'fa-lock'
                ],
                [
                    'label' => 'Batch File Processing',
                    'route' => 'batch-file-processing',
                    'icon' => 'fa-file-arrow-up'
                ],
            ]"
        />

        <main class="main users-main">

            <x-layout.topbar
                title="User Management"
                subtitle="Manage system users, roles, departments, and access levels"
                notification-count="6"
            />

            <section class="stats-grid users-stats-grid">

                <x-ui.summary-card
                    label="Total Users"
                    value="{{ $totalUsers ?? 0 }}"
                    small="Registered accounts"
                    icon="fa-users"
                    color="gray"
                />

                <x-ui.summary-card
                    label="Active"
                    value="{{ $activeUsers ?? 0 }}"
                    small="Allowed to login"
                    icon="fa-circle-check"
                    color="green"
                />

                <x-ui.summary-card
                    label="Inactive"
                    value="{{ $inactiveUsers ?? 0 }}"
                    small="Temporarily disabled"
                    icon="fa-circle-xmark"
                    color="red"
                />

                <x-ui.summary-card
                    label="Pending"
                    value="{{ $pendingUsers ?? 0 }}"
                    small="Waiting activation"
                    icon="fa-clock"
                    color="yellow"
                />

            </section>

            <section class="table-card users-card users-table-card">

                <div class="section-header users-section-header">
                    <div>
                        <h2>System User Accounts</h2>
                        <p>Manage account creation, department assignment, and role access.</p>
                    </div>
                </div>

                <x-ui.table-toolbar
                    :action="route('admin.users')"
                    class="toolbar users-toolbar"
                    search-placeholder="Search by name, email, role, or department..."
                    button-id="openAddUserModal"
                    button-label="Add User"
                >
                    <div class="filter-group">
                        <label for="departmentFilter">Department</label>

                        <select
                            name="department"
                            id="departmentFilter"
                            onchange="this.form.submit()"
                        >
                            <option
                                value="All Departments"
                                {{ request('department', 'All Departments') === 'All Departments' ? 'selected' : '' }}
                            >
                                All Departments
                            </option>

                            @foreach($departmentOptions as $department)
                                <option
                                    value="{{ $department }}"
                                    {{ request('department') === $department ? 'selected' : '' }}
                                >
                                    {{ $department }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="roleFilter">Role</label>

                        <select
                            name="role"
                            id="roleFilter"
                            onchange="this.form.submit()"
                        >
                            <option
                                value="All Roles"
                                {{ request('role', 'All Roles') === 'All Roles' ? 'selected' : '' }}
                            >
                                All Roles
                            </option>

                            @foreach(($roles ?? []) as $roleValue => $roleLabel)
                                <option
                                    value="{{ $roleValue }}"
                                    {{ request('role') === $roleValue ? 'selected' : '' }}
                                >
                                    {{ $roleLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="statusFilter">Status</label>

                        <select
                            name="status"
                            id="statusFilter"
                            onchange="this.form.submit()"
                        >
                            <option
                                value="All Status"
                                {{ request('status', 'All Status') === 'All Status' ? 'selected' : '' }}
                            >
                                All Status
                            </option>

                            @foreach(($statuses ?? []) as $status)
                                <option
                                    value="{{ $status }}"
                                    {{ request('status') === $status ? 'selected' : '' }}
                                >
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </x-ui.table-toolbar>

                <div class="users-table-wrap">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse(($users ?? []) as $user)
                                @php
                                    $nameParts = collect(explode(' ', $user->name ?? ''))
                                        ->filter()
                                        ->values();

                                    $initials = strtoupper(
                                        substr($nameParts->get(0, ''), 0, 1) .
                                        substr($nameParts->get(1, ''), 0, 1)
                                    );

                                    $initials = $initials ?: 'U';

                                    $avatarColors = [
                                        'green',
                                        'blue',
                                        'violet',
                                        'orange',
                                        'red',
                                        'teal',
                                        'sky',
                                        'purple'
                                    ];

                                    $avatarColor = $avatarColors[
                                        $loop->index % count($avatarColors)
                                    ];

                                    $roleDisplay = $formatRole($user);

                                    $roleClass = strtolower(
                                        str_replace([' ', '_'], '-', $roleDisplay)
                                    );

                                    $lastLoginDisplay = $user->last_login_at
                                        ? \Carbon\Carbon::parse($user->last_login_at)->format('m/d/y h:i A')
                                        : 'Never';

                                    $isOwnAccount = auth()->id() === $user->id;
                                @endphp

                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="avatar {{ $avatarColor }}">
                                                {{ $initials }}
                                            </div>

                                            <div>
                                                <strong>{{ $user->name }}</strong>
                                                <span>{{ $user->email }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <x-ui.status-badge
                                            :status="$roleDisplay"
                                            type="user"
                                            :class="$roleClass"
                                        />
                                    </td>

                                    <td>
                                        {{ $user->department ?? '—' }}
                                    </td>

                                    <td>
                                        <x-ui.status-badge
                                            :status="$user->status ?? 'Inactive'"
                                            type="user"
                                        />
                                    </td>

                                    <td>
                                        {{ $lastLoginDisplay }}
                                    </td>

                                    <td>
                                        <div class="action-menu">

                                            <button
                                                type="button"
                                                class="open-view-user-modal action-view"
                                                title="View"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $roleDisplay }}"
                                                data-role-value="{{ $user->role }}"
                                                data-department="{{ $user->department }}"
                                                data-status="{{ $user->status }}"
                                                data-last-login="{{ $lastLoginDisplay }}"
                                                data-initials="{{ $initials }}"
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="open-edit-user-modal action-edit"
                                                title="Edit"
                                                data-update-url="{{ route('admin.users.update', $user) }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $user->role }}"
                                                data-department="{{ $user->department }}"
                                                data-status="{{ $user->status }}"
                                            >
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>

                                            <button
                                                type="button"
                                                class="open-reset-password-modal action-reset"
                                                title="Reset Password"
                                                data-reset-url="{{ route('admin.users.reset-password', $user) }}"
                                                data-name="{{ $user->name }}"
                                            >
                                                <i class="fa-solid fa-key"></i>
                                            </button>

                                            @if($user->status === 'Active')
                                                <form
                                                    action="{{ route('admin.users.update-status', $user) }}"
                                                    method="POST"
                                                >
                                                    @csrf
                                                    @method('PATCH')

                                                    <input type="hidden" name="status" value="Inactive">

                                                    <button
                                                        type="submit"
                                                        class="action-deactivate"
                                                        title="Deactivate"
                                                        @if($isOwnAccount) disabled @endif
                                                    >
                                                        <i class="fa-solid fa-user-slash"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form
                                                    action="{{ route('admin.users.update-status', $user) }}"
                                                    method="POST"
                                                >
                                                    @csrf
                                                    @method('PATCH')

                                                    <input type="hidden" name="status" value="Active">

                                                    <button
                                                        type="submit"
                                                        class="action-activate"
                                                        title="Activate"
                                                    >
                                                        <i class="fa-solid fa-user-check"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            @if(! $isOwnAccount)
                                                <form
                                                    action="{{ route('admin.users.destroy', $user) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="submit"
                                                        class="action-delete"
                                                        title="Delete"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif

                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="6"
                                    message="No users found."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($users))
                    <x-ui.table-footer :items="$users" />
                @endif

            </section>

            <footer class="admin-footer">
                © 2026 FROMS. All rights reserved.
            </footer>

        </main>
    </div>

    {{-- ADD / EDIT USER MODAL --}}
    <div class="admin-modal-overlay" id="userFormModal">
        <div class="admin-user-modal">

            <div class="admin-modal-header">
                <div>
                    <h2 id="userFormModalTitle">Add User Account</h2>
                    <p id="userFormModalSubtitle">
                        Create a new system account and assign role access.
                    </p>
                </div>

                <button type="button" class="modal-close-btn" id="closeUserFormModal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form
                class="admin-user-form"
                id="userForm"
                method="POST"
                action="{{ route('admin.users.store') }}"
                data-store-url="{{ route('admin.users.store') }}"
            >
                @csrf

                <input type="hidden" name="_method" id="userFormMethod" value="POST">

                <div class="form-grid">

                    <div class="form-group">
                        <label>Full Name</label>
                        <input
                            type="text"
                            name="name"
                            id="userNameInput"
                            placeholder="Enter full name"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            id="userEmailInput"
                            placeholder="Enter email address"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input
                            type="password"
                            name="password"
                            id="userPasswordInput"
                            placeholder="Enter password"
                        >
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="userDepartmentInput" required>
                            <option value="">Select Department</option>

                            @foreach($departmentOptions as $department)
                                <option value="{{ $department }}">
                                    {{ $department }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="userRoleInput" required>
                            <option value="">Select Role</option>

                            @foreach(($roles ?? []) as $roleValue => $roleLabel)
                                <option value="{{ $roleValue }}">
                                    {{ $roleLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="userStatusInput" required>
                            @foreach(($statuses ?? []) as $status)
                                <option value="{{ $status }}">
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="admin-modal-footer modal-actions">
                    <button type="button" class="secondary-btn btn-cancel" id="cancelUserFormModal">
                        Cancel
                    </button>

                    <button type="submit" class="primary-btn btn-save" id="userFormSaveBtn">
                        Save User
                    </button>
                </div>

            </form>

        </div>
    </div>

    {{-- VIEW USER MODAL --}}
    <div class="admin-modal-overlay" id="viewUserModal">
        <div class="admin-user-modal view-user-modal">

            <div class="admin-modal-header">
                <div>
                    <h2>User Account Details</h2>
                    <p>Read-only account information and role assignment.</p>
                </div>

                <button type="button" class="modal-close-btn" id="closeViewUserModal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="view-user-top">
                <div class="view-avatar" id="viewUserInitials">--</div>

                <div>
                    <h3 id="viewUserName">—</h3>
                    <p id="viewUserEmail">—</p>
                </div>
            </div>

            <div class="view-user-grid">

                <div class="view-field">
                    <label>Role</label>
                    <div id="viewUserRole">—</div>
                </div>

                <div class="view-field">
                    <label>Department</label>
                    <div id="viewUserDepartment">—</div>
                </div>

                <div class="view-field">
                    <label>Status</label>
                    <div id="viewUserStatus">—</div>
                </div>

                <div class="view-field">
                    <label>Last Login</label>
                    <div id="viewUserLastLogin">—</div>
                </div>

            </div>

            <div class="admin-modal-footer modal-actions">
                <button type="button" class="secondary-btn btn-cancel" id="closeViewUserModalBottom">
                    Close
                </button>
            </div>

        </div>
    </div>

    {{-- RESET PASSWORD MODAL --}}
    <div class="admin-modal-overlay" id="resetPasswordModal">
        <div class="admin-user-modal">

            <div class="admin-modal-header">
                <div>
                    <h2>Reset Password</h2>
                    <p id="resetPasswordSubtitle">
                        Set a new password for this user account.
                    </p>
                </div>

                <button type="button" class="modal-close-btn" id="closeResetPasswordModal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form
                class="admin-user-form"
                id="resetPasswordForm"
                method="POST"
                action="#"
            >
                @csrf
                @method('PATCH')

                <div class="form-grid">

                    <div class="form-group">
                        <label>New Password</label>
                        <input
                            type="password"
                            name="password"
                            id="resetPasswordInput"
                            placeholder="Enter new password"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input
                            type="password"
                            name="password_confirmation"
                            id="resetPasswordConfirmInput"
                            placeholder="Confirm new password"
                            required
                        >
                    </div>

                </div>

                <div class="admin-modal-footer modal-actions">
                    <button type="button" class="secondary-btn btn-cancel" id="cancelResetPasswordModal">
                        Cancel
                    </button>

                    <button type="submit" class="primary-btn btn-save">
                        Reset Password
                    </button>
                </div>

            </form>

        </div>
    </div>

</x-layout.app>
