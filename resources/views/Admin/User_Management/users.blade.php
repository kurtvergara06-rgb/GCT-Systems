<x-layout.app
    title="FROMS - Account Management"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/User_Management/users.css',
        'resources/js/Admin/User_Management/users.js'
    ]"
>
    @php
        $departmentOptions = $departments ?? [
            'Maintenance',
            'Warehouse',
            'Purchase',
            'Operation',
        ];

        $formatRole = function ($user) {
            $department = trim($user->department ?? '');
            $role = strtolower(trim($user->role ?? ''));

            if (strtolower($department) === 'admin') {
                return $role === 'head'
                    ? 'System Admin'
                    : 'Admin Staff';
            }

            if ($department === '') {
                return ucfirst($role ?: 'Account');
            }

            return $department . ' ' . ucfirst($role ?: 'Staff');
        };
    @endphp

    <div class="app admin-users-app">
        <x-layout.sidebar department="Admin" />

        <main class="main users-main records-page">
            <x-layout.topbar
                title="Account Management"
                subtitle="Manage system accounts, department assignments, roles, and access"
                notification-count="6"
            />

            <x-ui.ajax-region name="summary" class="stats-grid users-stats-grid">
                <x-ui.summary-card
                    label="Total Accounts"
                    value="{{ $totalUsers ?? 0 }}"
                    small="Registered system accounts"
                    icon="fa-users"
                    color="gray"
                />

                <x-ui.summary-card
                    label="Active"
                    value="{{ $activeUsers ?? 0 }}"
                    small="Allowed to sign in"
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
            </x-ui.ajax-region>

            <x-ui.ajax-region name="records" class="records-card users-card users-table-card">
                <x-ui.section-header
                    title="System Accounts"
                    subtitle="Create accounts and manage department, role, status, and access assignments."
                    class="users-section-header"
                />

                <x-ui.table-toolbar
                    :action="route('admin.users')"
                    class="records-toolbar users-toolbar"
                    search-placeholder="Search by name, email, role, or department..."
                    button-id="openAddUserModal"
                    button-label="Add Account"
                    button-icon="fa-user-plus"
                >
                    <div class="filter-group">
                        <select
                            name="department"
                            id="departmentFilter"
                            onchange="this.form.submit()"
                            aria-label="Department"
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

                <div class="records-table-wrap users-table-wrap table-wrap">
                    <table class="records-table users-table">
                        <thead>
                            <tr>
                                <th>Account</th>
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
                                    ) ?: 'U';

                                    $avatarTones = [
                                        'green',
                                        'blue',
                                        'violet',
                                        'orange',
                                        'red',
                                        'teal',
                                        'sky',
                                        'purple'
                                    ];

                                    $avatarTone = $avatarTones[
                                        $loop->index % count($avatarTones)
                                    ];

                                    $roleDisplay = $formatRole($user);
                                    $roleClass = strtolower(
                                        str_replace([' ', '_'], '-', $roleDisplay)
                                    );

                                    $lastLoginDisplay = $user->last_login_at
                                        ? \Carbon\Carbon::parse($user->last_login_at)->format('M d, Y · g:i A')
                                        : 'Never';

                                    $isOwnAccount = auth()->id() === $user->id;
                                @endphp

                                <tr>
                                    <td>
                                        <x-ui.record-identity
                                            :title="$user->name"
                                            :subtitle="$user->email"
                                            :initials="$initials"
                                            :tone="$avatarTone"
                                        />
                                    </td>

                                    <td>
                                        <x-ui.status-badge
                                            :status="$roleDisplay"
                                            type="user"
                                            :class="$roleClass"
                                        />
                                    </td>

                                    <td>{{ $user->department ?? '—' }}</td>

                                    <td>
                                        <x-ui.status-badge
                                            :status="$user->status ?? 'Inactive'"
                                            type="user"
                                        />
                                    </td>

                                    <td>{{ $lastLoginDisplay }}</td>

                                    <td>
                                        <div class="record-actions action-menu">
                                            <x-ui.action-button
                                                type="view"
                                                class="open-view-user-modal action-view"
                                                title="View Account"
                                                data-icon-only
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $roleDisplay }}"
                                                data-role-value="{{ $user->role }}"
                                                data-department="{{ $user->department }}"
                                                data-status="{{ $user->status }}"
                                                data-last-login="{{ $lastLoginDisplay }}"
                                                data-initials="{{ $initials }}"
                                            />

                                            <x-ui.action-button
                                                type="edit"
                                                class="open-edit-user-modal action-edit"
                                                title="Edit Account"
                                                data-icon-only
                                                data-update-url="{{ route('admin.users.update', $user) }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $user->role }}"
                                                data-department="{{ $user->department }}"
                                                data-status="{{ $user->status }}"
                                            />

                                            <x-ui.action-button
                                                type="reset"
                                                class="open-reset-password-modal action-reset"
                                                title="Reset Password"
                                                data-icon-only
                                                data-reset-url="{{ route('admin.users.reset-password', $user) }}"
                                                data-name="{{ $user->name }}"
                                            />

                                            @if($user->status === 'Active')
                                                <form
                                                    action="{{ route('admin.users.update-status', $user) }}"
                                                    method="POST"
                                                    data-confirm-form
                                                    data-confirm-title="Deactivate Account?"
                                                    data-confirm-message="Are you sure you want to deactivate {{ $user->name }}?"
                                                    data-confirm-button="Yes, Deactivate"
                                                    data-confirm-type="status"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Inactive">

                                                    <x-ui.action-button
                                                        type="deactivate"
                                                        button-type="submit"
                                                        class="action-deactivate"
                                                        title="Deactivate Account"
                                                        :disabled="$isOwnAccount"
                                                        data-icon-only
                                                    />
                                                </form>
                                            @else
                                                <form
                                                    action="{{ route('admin.users.update-status', $user) }}"
                                                    method="POST"
                                                    data-confirm-form
                                                    data-confirm-title="Activate Account?"
                                                    data-confirm-message="Are you sure you want to activate {{ $user->name }}?"
                                                    data-confirm-button="Yes, Activate"
                                                    data-confirm-type="approve"
                                                >
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Active">

                                                    <x-ui.action-button
                                                        type="activate"
                                                        button-type="submit"
                                                        class="action-activate"
                                                        title="Activate Account"
                                                        data-icon-only
                                                    />
                                                </form>
                                            @endif

                                            @if(! $isOwnAccount)
                                                <form
                                                    action="{{ route('admin.users.destroy', $user) }}"
                                                    method="POST"
                                                    data-confirm-form
                                                    data-confirm-title="Delete Account?"
                                                    data-confirm-message="Are you sure you want to delete {{ $user->name }}? This action cannot be undone."
                                                    data-confirm-button="Yes, Delete"
                                                    data-confirm-type="delete"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <x-ui.action-button
                                                        type="delete"
                                                        button-type="submit"
                                                        class="action-delete"
                                                        title="Delete Account"
                                                        data-icon-only
                                                    />
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="6"
                                    message="No system accounts found."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(isset($users))
                    <x-ui.table-footer :items="$users" />
                @endif
            </x-ui.ajax-region>

            <footer class="admin-footer">
                © 2026 FROMS. All rights reserved.
            </footer>
        </main>
    </div>

    {{-- ADD / EDIT ACCOUNT MODAL --}}
    <div class="admin-modal-overlay" id="userFormModal">
        <div class="admin-user-modal">
            <div class="admin-modal-header">
                <div>
                    <h2 id="userFormModalTitle">Add System Account</h2>
                    <p id="userFormModalSubtitle">Create a new account and assign department and role access.</p>
                </div>

                <button type="button" class="modal-close-btn" id="closeUserFormModal" data-icon-only>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form
                class="admin-user-form"
                id="userForm"
                method="POST"
                action="{{ route('admin.users.store') }}"
                data-store-url="{{ route('admin.users.store') }}"
                data-confirm-form
                data-confirm-title="Create Account?"
                data-confirm-message="Are you sure you want to create this system account?"
                data-confirm-button="Yes, Create Account"
                data-confirm-type="create"
            >
                @csrf

                <input type="hidden" name="_method" id="userFormMethod" value="POST">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" id="userNameInput" placeholder="Enter full name" required>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="userEmailInput" placeholder="Enter email address" required>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" id="userPasswordInput" placeholder="Enter password">
                    </div>

                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" id="userDepartmentInput" required>
                            <option value="">Select Department</option>
                            @foreach($departmentOptions as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" id="userRoleInput" required>
                            <option value="">Select Role</option>
                            @foreach(($roles ?? []) as $roleValue => $roleLabel)
                                <option value="{{ $roleValue }}">{{ $roleLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="userStatusInput" required>
                            @foreach(($statuses ?? []) as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="admin-modal-footer modal-actions">
                    <button type="button" class="secondary-btn btn-cancel" id="cancelUserFormModal">Cancel</button>
                    <button type="submit" class="primary-btn btn-save" id="userFormSaveBtn">Save Account</button>
                </div>
            </form>
        </div>
    </div>

    {{-- VIEW ACCOUNT MODAL --}}
    <div class="admin-modal-overlay" id="viewUserModal">
        <div class="admin-user-modal view-user-modal">
            <div class="admin-modal-header">
                <div>
                    <h2>Account Details</h2>
                    <p>Read-only account information and access assignment.</p>
                </div>

                <button type="button" class="modal-close-btn" id="closeViewUserModal" data-icon-only>
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
                <div class="view-field"><label>Role</label><div id="viewUserRole">—</div></div>
                <div class="view-field"><label>Department</label><div id="viewUserDepartment">—</div></div>
                <div class="view-field"><label>Status</label><div id="viewUserStatus">—</div></div>
                <div class="view-field"><label>Last Login</label><div id="viewUserLastLogin">—</div></div>
            </div>

            <div class="admin-modal-footer modal-actions">
                <button type="button" class="secondary-btn btn-cancel" id="closeViewUserModalBottom">Close</button>
            </div>
        </div>
    </div>

    {{-- RESET PASSWORD MODAL --}}
    <div class="admin-modal-overlay" id="resetPasswordModal">
        <div class="admin-user-modal">
            <div class="admin-modal-header">
                <div>
                    <h2>Reset Password</h2>
                    <p id="resetPasswordSubtitle">Set a new password for this system account.</p>
                </div>

                <button type="button" class="modal-close-btn" id="closeResetPasswordModal" data-icon-only>
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form
                class="admin-user-form"
                id="resetPasswordForm"
                method="POST"
                action="#"
                data-confirm-form
                data-confirm-title="Reset Account Password?"
                data-confirm-message="Are you sure you want to reset this account password?"
                data-confirm-button="Yes, Reset Password"
                data-confirm-type="warning"
            >
                @csrf

                <div class="form-grid">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" id="resetPasswordInput" placeholder="Enter new password" required>
                    </div>

                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation" id="resetPasswordConfirmInput" placeholder="Confirm new password" required>
                    </div>
                </div>

                <div class="admin-modal-footer modal-actions">
                    <button type="button" class="secondary-btn btn-cancel" id="cancelResetPasswordModal">Cancel</button>
                    <button type="submit" class="primary-btn btn-save">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</x-layout.app>
