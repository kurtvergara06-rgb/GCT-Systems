<x-layout.app
  title="FROMS - User Management"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Admin/users.css',
    'resources/js/Admin/users.js'
  ]"
>
  @php
    $authUser = auth()->user();

    $normalizeRoleLabel = function ($role) {
      $role = strtolower(trim($role ?? ''));
      $role = str_replace(['_', '-'], ' ', $role);

      if (str_contains($role, 'head')) {
        return 'Head';
      }

      if (str_contains($role, 'staff')) {
        return 'Staff';
      }

      return ucwords($role ?: 'User');
    };

    $formatRole = function ($user) use ($normalizeRoleLabel) {
      $department = trim($user->department ?? '');
      $roleLabel = $normalizeRoleLabel($user->role ?? '');

      if (strtolower($department) === 'admin') {
        return 'System Admin';
      }

      if ($department === '') {
        return $roleLabel;
      }

      return $department . ' ' . $roleLabel;
    };

    $sidebarName = $authUser?->name ?? 'System Admin';

    $sidebarDepartment = trim($authUser?->department ?? 'Admin');
    $sidebarRoleLabel = $normalizeRoleLabel($authUser?->role ?? 'head');

    if (strtolower($sidebarDepartment) === 'admin') {
      $sidebarRole = 'System Admin';
    } else {
      $sidebarRole = $sidebarDepartment . ' ' . $sidebarRoleLabel;
    }
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
      ]"
    />

    <main class="main users-main">

      <x-layout.topbar
        title="User Management"
        subtitle="Manage system users, roles, departments, and access levels"
        notification-count="6"
      />

      @if(session('success') || $errors->any())
        <div class="feedback-modal-overlay show" id="feedbackModal">
          <div class="feedback-modal">

            <div class="feedback-icon {{ session('success') ? 'success' : 'error' }}">
              @if(session('success'))
                <i class="fa-solid fa-check"></i>
              @else
                <i class="fa-solid fa-xmark"></i>
              @endif
            </div>

            <h2>
              {{ session('success') ? 'Success!' : 'Error!' }}
            </h2>

            <p>
              {{ session('success') ?? $errors->first() }}
            </p>

            <button type="button" class="feedback-ok-btn" id="closeFeedbackModal">
              Okay
            </button>

          </div>
        </div>
      @endif

      <section class="stats-grid">

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

      <section class="users-card">

        <div class="users-card-header">
          <div>
            <h2>System User Accounts</h2>
            <p>Manage account creation, department assignment, and role access.</p>
          </div>

          <button type="button" class="add-user-btn" id="openAddUserModal">
            <i class="fa-solid fa-plus"></i>
            Add User
          </button>
        </div>

        <form action="{{ route('admin.users') }}" method="GET" class="users-toolbar">

          <div class="users-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input
              type="text"
              name="search"
              value="{{ request('search') }}"
              placeholder="Search by name, email, role, or department..."
            >
          </div>

          <select name="department" onchange="this.form.submit()">
            <option value="All Departments" {{ request('department', 'All Departments') === 'All Departments' ? 'selected' : '' }}>
              All Departments
            </option>

            @foreach(($departments ?? []) as $department)
              <option value="{{ $department }}" {{ request('department') === $department ? 'selected' : '' }}>
                {{ $department }}
              </option>
            @endforeach
          </select>

          <select name="role" onchange="this.form.submit()">
            <option value="All Roles" {{ request('role', 'All Roles') === 'All Roles' ? 'selected' : '' }}>
              All Roles
            </option>

            @foreach(($roles ?? []) as $roleValue => $roleLabel)
              <option value="{{ $roleValue }}" {{ request('role') === $roleValue ? 'selected' : '' }}>
                {{ $roleLabel }}
              </option>
            @endforeach
          </select>

          <select name="status" onchange="this.form.submit()">
            <option value="All Status" {{ request('status', 'All Status') === 'All Status' ? 'selected' : '' }}>
              All Status
            </option>

            @foreach(($statuses ?? []) as $status)
              <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                {{ $status }}
              </option>
            @endforeach
          </select>

        </form>

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
                  $nameParts = collect(explode(' ', $user->name ?? ''))->filter()->values();

                  $initials = strtoupper(
                    substr($nameParts->get(0, ''), 0, 1) .
                    substr($nameParts->get(1, ''), 0, 1)
                  );

                  $initials = $initials ?: 'U';

                  $avatarColors = ['green', 'blue', 'violet', 'orange', 'red', 'teal', 'sky', 'purple'];
                  $avatarColor = $avatarColors[$loop->index % count($avatarColors)];

                  $roleDisplay = $formatRole($user);
                  $roleClass = strtolower(str_replace([' ', '_'], '-', $roleDisplay));
                  $statusClass = strtolower($user->status ?? 'inactive');

                  $lastLoginDisplay = $user->last_login_at
                      ? \Carbon\Carbon::parse($user->last_login_at)->format('m/d/y h:i A')
                      : 'Never';
                @endphp

                <tr>
                  <td>
                    <div class="user-info">
                      <div class="avatar {{ $avatarColor }}">{{ $initials }}</div>

                      <div>
                        <strong>{{ $user->name }}</strong>
                        <span>{{ $user->email }}</span>
                      </div>
                    </div>
                  </td>

                  <td>
                    <span class="role-pill {{ $roleClass }}">
                      {{ $roleDisplay }}
                    </span>
                  </td>

                  <td>{{ $user->department ?? '—' }}</td>

                  <td>
                    <span class="status-pill {{ $statusClass }}">
                      {{ $user->status ?? 'Inactive' }}
                    </span>
                  </td>

                  <td>
                    {{ $lastLoginDisplay }}
                  </td>

                  <td>
                    <div class="action-menu">

                      <button
                        type="button"
                        class="open-view-user-modal"
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
                        class="open-edit-user-modal"
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
                        class="open-reset-password-modal"
                        title="Reset Password"
                        data-reset-url="{{ route('admin.users.reset-password', $user) }}"
                        data-name="{{ $user->name }}"
                      >
                        <i class="fa-solid fa-key"></i>
                      </button>

                      @if($user->status === 'Active')
                        <form action="{{ route('admin.users.update-status', $user) }}" method="POST">
                          @csrf
                          @method('PATCH')

                          <input type="hidden" name="status" value="Inactive">

                          <button type="submit" title="Deactivate">
                            <i class="fa-solid fa-user-slash"></i>
                          </button>
                        </form>
                      @else
                        <form action="{{ route('admin.users.update-status', $user) }}" method="POST">
                          @csrf
                          @method('PATCH')

                          <input type="hidden" name="status" value="Active">

                          <button type="submit" title="Activate">
                            <i class="fa-solid fa-user-check"></i>
                          </button>
                        </form>
                      @endif

                    </div>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="empty-users">
                    No users found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if(isset($users))
          <div class="users-footer">
            <div>
              Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} entries
            </div>

            <div>
              {{ $users->links() }}
            </div>
          </div>
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
          <p id="userFormModalSubtitle">Create a new system account and assign role access.</p>
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

              @foreach(($departments ?? []) as $department)
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

        <div class="admin-modal-footer">
          <button type="button" class="btn-cancel" id="cancelUserFormModal">
            Cancel
          </button>

          <button type="submit" class="btn-save" id="userFormSaveBtn">
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

      <div class="admin-modal-footer">
        <button type="button" class="btn-cancel" id="closeViewUserModalBottom">
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
          <p id="resetPasswordSubtitle">Set a new password for this user account.</p>
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

        <div class="admin-modal-footer">
          <button type="button" class="btn-cancel" id="cancelResetPasswordModal">
            Cancel
          </button>

          <button type="submit" class="btn-save">
            Reset Password
          </button>
        </div>

      </form>

    </div>
  </div>

</x-layout.app>