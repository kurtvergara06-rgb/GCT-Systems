<x-layout.app
    title="{{ !empty($applicationsPage) ? 'FROMS - Applications' : 'FROMS - Accounts' }}"
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
            $rawRole = trim($user->role ?? '');
            $roleLower = strtolower($rawRole);
            $deptLower = strtolower($department);

            if ($deptLower === 'admin') {
                return $roleLower === 'head'
                    ? 'System Admin'
                    : 'Admin Staff';
            }

            // If the role already contains or starts with the department name, avoid repeating it
            if ($department !== '' && str_starts_with($roleLower, $deptLower)) {
                $cleaned = trim(substr($rawRole, strlen($department)));
                return $department . ' ' . (ucfirst($cleaned) ?: 'Staff');
            }

            if ($department === '') {
                return ucfirst($rawRole ?: 'Account');
            }

            return $department . ' ' . ucfirst($rawRole ?: 'Staff');
        };
    @endphp

    <div class="app admin-users-app">
        <x-layout.sidebar department="Admin" />

        <main class="main users-main records-page">
            <x-layout.topbar
                title="{{ !empty($applicationsPage) ? 'Applications' : 'Account Management' }}"
                subtitle="{{ !empty($applicationsPage) ? 'Review employee registrations, verify details, and approve or reject applications' : 'Manage registered accounts, departments, roles, and access' }}"
                notification-count="6"
            />

            <x-ui.ajax-region name="summary" class="stats-grid users-stats-grid">
                @if(!empty($applicationsPage))
                <x-ui.summary-card label="Pending Applications" value="{{ $pendingUsers ?? 0 }}" small="Awaiting review" icon="fa-user-clock" color="yellow" />
                <x-ui.summary-card label="Rejected Applications" value="{{ $rejectedUsers ?? 0 }}" small="Not approved" icon="fa-circle-xmark" color="red" />
                <x-ui.summary-card label="Verified Pending" value="{{ $verifiedPendingUsers ?? 0 }}" small="Email verified; awaiting decision" icon="fa-envelope-circle-check" color="green" />
                <x-ui.summary-card label="Unverified Pending" value="{{ $unverifiedPendingUsers ?? 0 }}" small="Email verification incomplete" icon="fa-envelope" color="gray" />
                @else
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
                @endif
            </x-ui.ajax-region>

            <x-ui.ajax-region name="records" class="records-card users-card users-table-card {{ empty($applicationsPage) ? 'users-card--accounts' : 'users-card--applications' }}">
                <x-ui.section-header
                    title="{{ !empty($applicationsPage) ? 'Employee Applications' : 'System Accounts' }}"
                    subtitle="{{ !empty($applicationsPage) ? 'Review registrations awaiting a decision and view rejected applications.' : 'Manage existing user accounts, roles, and access assignments.' }}"
                    class="users-section-header"
                >
                </x-ui.section-header>

                <div class="users-record-controls">
                @if(!empty($applicationsPage))
                <nav class="application-status-tabs" aria-label="Filter employee applications">
                    @foreach(['Pending Applications' => 'Pending', 'Rejected' => 'Rejected'] as $label => $statusValue)
                        <a href="{{ route('admin.applications', ['status' => $statusValue]) }}"
                           class="application-status-tab {{ request('status', 'Pending') === $statusValue ? 'is-active' : '' }}"
                           @if(request('status', 'Pending') === $statusValue) aria-current="page" @endif>
                            {{ $label }}
                            @if($statusValue === 'Pending') <span class="application-status-count">{{ $pendingUsers }}</span> @endif
                        </a>
                    @endforeach
                </nav>
                @endif
                <x-ui.table-toolbar
                     :action="!empty($applicationsPage) ? route('admin.applications') : route('admin.users')"
                    class="records-toolbar users-toolbar"
                    search-placeholder="Search by name, email, role, or department..."
                    search-label="Search Accounts"
                    :filter-count="(int) (request()->filled('department') && request('department') !== 'All Departments') + (int) (request()->filled('role') && request('role') !== 'All Roles') + (int) (request()->filled('status') && request('status') !== 'All Status')"
                    filter-title="Filter Accounts"
                    :sort-options="['created_at' => 'Created Date', 'name' => 'Account Name', 'email' => 'Email', 'department' => 'Department', 'role' => 'Role', 'status' => 'Status']"
                    default-sort="created_at"
                    default-direction="desc"
                    :show-button="false"
                >
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Department" name="department" default="All Departments"><option value="All Departments">All Departments</option>@foreach($departmentOptions as $department)<option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>@endforeach</x-ui.table-toolbar-field>
                        <x-ui.table-toolbar-field label="Role" name="role" default="All Roles"><option value="All Roles">All Roles</option>@foreach(($roles ?? []) as $roleValue => $roleLabel)<option value="{{ $roleValue }}" @selected(request('role') === $roleValue)>{{ $roleLabel }}</option>@endforeach</x-ui.table-toolbar-field>
                        <x-ui.table-toolbar-field label="Status" name="status" default="All Status"><option value="All Status">All Statuses</option>@foreach(($statuses ?? []) as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>
                </div>

                <div class="records-table-wrap users-table-wrap table-wrap">
                    <table class="records-table users-table">
                        <thead>
                            <tr>
                                @if(!empty($applicationsPage))
                                    <th>Applicant</th>
                                    <th>Date Applied</th>
                                    <th>Email Verification</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                @else
                                <th>User ID</th>
                                <th>Account</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                                @endif
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

                                @if(!empty($applicationsPage))
                                <tr>
                                    <td>
                                        <x-ui.record-identity :title="$user->name" :subtitle="$user->email" :initials="$initials" :tone="$avatarTone" :image-url="$user->profilePhotoUrl()" />
                                    </td>
                                    <td>{{ $user->created_at?->format('M d, Y · g:i A') ?? 'Not recorded' }}</td>
                                    <td>
                                        <span class="application-verification {{ $user->hasVerifiedEmail() ? 'is-verified' : 'is-unverified' }}">
                                            {{ $user->hasVerifiedEmail() ? 'Verified' : 'Not Verified' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(in_array(strtolower(trim((string) $user->department)), ['unassigned', ''], true))
                                            <span class="application-department-unassigned"><i class="fa-solid fa-circle-minus" aria-hidden="true"></i> Unassigned</span>
                                        @else
                                            {{ $user->department }}
                                        @endif
                                    </td>
                                    <td><x-ui.status-badge :status="$user->status ?? 'Pending'" type="user" /></td>
                                    <td>
                                        @if($user->status === 'Pending' && auth()->user()?->hasSystemPermission('administration', 'full_control'))
                                            <button type="button" class="application-review-button"
                                                data-review-application
                                                data-review-url="{{ route('admin.users.review-application', $user) }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-applied="{{ $user->created_at?->format('M d, Y · g:i A') }}"
                                                data-verified="{{ $user->hasVerifiedEmail() ? '1' : '0' }}">
                                                <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Review
                                            </button>
                                        @else
                                            <span class="application-action-note">{{ $user->status === 'Rejected' ? 'Rejected' : 'Review restricted' }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @else
                                <tr>
                                    <td>
                                        <x-ui.id-badge :value="'USR-' . str_pad($user->id, 3, '0', STR_PAD_LEFT)" />
                                    </td>

                                    <td>
                                        <x-ui.record-identity
                                            :title="$user->name"
                                            :subtitle="$user->email"
                                            :initials="$initials"
                                            :tone="$avatarTone"
                                            :image-url="$user->profilePhotoUrl()"
                                        />
                                    </td>

                                    <td>
                                        <x-ui.status-badge
                                            :status="$roleDisplay"
                                            type="user"
                                            :class="$roleClass"
                                        />
                                    </td>

                                    <td>
                                        <span class="user-dept-text">{{ $user->department ?? '—' }}</span>
                                    </td>

                                    <td>
                                        <x-ui.status-badge
                                            :status="$user->status ?? 'Inactive'"
                                            type="user"
                                        />
                                    </td>

                                    <td>
                                        @if($user->last_login_at)
                                            <div class="user-last-login">
                                                <span class="login-date">{{ \Carbon\Carbon::parse($user->last_login_at)->format('M d, Y') }}</span>
                                                <small class="login-time">{{ \Carbon\Carbon::parse($user->last_login_at)->format('g:i A') }}</small>
                                            </div>
                                        @else
                                            <span class="user-login-never">Never</span>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="record-actions action-menu">
                                        @if($user->status === 'Pending' && auth()->user()?->hasSystemPermission('administration', 'full_control'))
                                            <button type="button" class="application-review-button"
                                                data-review-application
                                                data-review-url="{{ route('admin.users.review-application', $user) }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-applied="{{ $user->created_at?->format('M d, Y · g:i A') }}"
                                                data-verified="{{ $user->hasVerifiedEmail() ? '1' : '0' }}">
                                                <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Review
                                            </button>
                                        @endif

                                            <x-ui.action-button
                                                type="view"
                                                class="open-view-user-modal action-view"
                                                title="View Account"
                                                data-icon-only
                                                data-id="{{ $user->id }}"
                                                data-user-id="USR-{{ str_pad($user->id, 3, '0', STR_PAD_LEFT) }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $roleDisplay }}"
                                                data-role-value="{{ $user->role }}"
                                                data-department="{{ $user->department }}"
                                                data-status="{{ $user->status }}"
                                                data-last-login="{{ $lastLoginDisplay }}"
                                                data-initials="{{ $initials }}"
                                                data-avatar-url="{{ $user->profilePhotoUrl() }}"
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
                                            @elseif($user->status === 'Inactive')
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
                                @endif
                            @empty
                                <x-ui.empty-row
                                    colspan="{{ !empty($applicationsPage) ? 6 : 7 }}"
                                    icon="fa-users"
                                    message="{{ !empty($applicationsPage) ? 'No Applications Found' : 'No User Accounts Found' }}"
                                    description="{{ !empty($applicationsPage) ? 'No applications match the selected status or filters.' : 'No system user accounts match your search or department filter.' }}"
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

    {{-- PENDING APPLICATION REVIEW --}}
    @if(auth()->user()?->hasSystemPermission('administration', 'full_control'))
        <div class="admin-modal-overlay" id="applicationReviewModal" role="dialog" aria-modal="true" aria-labelledby="applicationReviewTitle">
            <div class="admin-user-modal application-review-dialog">
                <div class="admin-modal-header application-review-header">
                    <div>
                        <div class="application-review-eyebrow"><i class="fa-regular fa-address-card" aria-hidden="true"></i> EMPLOYEE ONBOARDING</div>
                        <h2 id="applicationReviewTitle">Review Employee Application</h2>
                        <p>Confirm the applicant's verified email and assign their department and role.</p>
                    </div>
                    <button type="button" class="modal-close-btn" data-close-application-review aria-label="Close application review"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <form id="applicationReviewForm" class="admin-user-form" method="POST">
                    @csrf
                    <section class="application-review-details" aria-label="Applicant information">
                        <div class="application-review-profile">
                            <span class="application-review-avatar" aria-hidden="true"><i class="fa-regular fa-user"></i></span>
                            <div class="application-review-identity">
                                <span class="application-review-section-label">APPLICANT DETAILS</span>
                                <strong id="applicationReviewName"></strong>
                                <span id="applicationReviewEmail"></span>
                            </div>
                        </div>
                        <div class="application-review-metadata">
                            <div class="application-review-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i><small id="applicationReviewApplied"></small></div>
                            <span class="application-review-status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span id="applicationReviewVerified" role="status"></span></span>
                        </div>
                    </section>
                    <div class="application-review-section-heading">
                        <div><h3>Access assignment</h3><p>Select the department and role for this employee.</p></div>
                    </div>
                    <div class="application-review-fields"><div class="form-group">
                        <label for="applicationReviewDepartment">Department <span aria-hidden="true">*</span></label>
                        <select name="department" id="applicationReviewDepartment" required>
                            <option value="">Select department</option>
                            @foreach($departmentOptions as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="applicationReviewRole">Role <span aria-hidden="true">*</span></label>
                        <select name="role" id="applicationReviewRole" required>
                            <option value="">Select role</option>
                            @foreach($roles as $roleKey => $roleName)
                                <option value="{{ $roleKey }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                    </div>
                    <div class="application-review-actions">
                        <button type="button" data-close-application-review><i class="fa-solid fa-xmark" aria-hidden="true"></i> Cancel</button>
                        <button type="submit" name="decision" value="reject" class="application-reject-button" formnovalidate
                            onclick="return confirm('Reject this employee application?')"><i class="fa-solid fa-ban" aria-hidden="true"></i> Reject</button>
                        <button type="submit" name="decision" value="approve" class="application-approve-button" id="applicationReviewApprove"><i class="fa-solid fa-check" aria-hidden="true"></i> Approve Application</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

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
                        <select name="department" id="userDepartmentInput" required>
                            <option value="">Select Department</option>
                            @foreach($departmentOptions as $department)
                                <option value="{{ $department }}">{{ $department }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
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
                <div class="view-avatar">
                    <img id="viewUserAvatarImage" src="" alt="" hidden>
                    <span id="viewUserInitials">--</span>
                </div>
                <div class="view-user-meta">
                    <div class="view-user-name-row">
                        <h3 id="viewUserName">—</h3>
                        <span id="viewUserUserId" class="system-id-badge">—</span>
                    </div>
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
