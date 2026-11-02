<x-layout.app
    title="FROMS - Roles & Permissions"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/User_Management/permissions.css',
        'resources/js/Admin/User_Management/permissions.js'
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main permissions-page">
            <x-layout.topbar
                title="Roles & Permissions"
                subtitle="Manage department roles, feature access, and employee permissions"
            />

            @if(! $permissionsReady)
                <section class="permission-note">
                    <div class="permission-note-icon">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div>
                        <strong>Role permissions database is not ready yet.</strong>
                        <p>Run the latest Laravel migration, then refresh this page.</p>
                    </div>
                </section>
            @else
                <nav class="permission-page-tabs" aria-label="Permissions sections">
                    <button type="button" class="permission-page-tab active" data-permission-tab="roles" aria-selected="true">
                        <i class="fa-solid fa-users-gear"></i> Department Roles
                    </button>
                    <button type="button" class="permission-page-tab" data-permission-tab="additional" aria-selected="false">
                        <i class="fa-solid fa-user-shield"></i> Additional Access
                    </button>
                </nav>
                <section class="permission-additional-workspace" id="permissionAdditionalAccess" hidden>
                    <div class="permission-access-intro">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <div><strong>Employee Additional Access</strong><p>Plan specific cross-department access without changing an employee's primary role. This is a read-only planning preview; no permissions will be saved.</p></div>
                        <span>Preview only</span>
                    </div>
                    <div class="permission-access-grid">
                        <section class="permission-access-card permission-access-employees">
                            <h2><span>1</span> <i class="fa-solid fa-users"></i> Select Employee</h2>
                            <p>Choose an existing active employee account.</p>
                            <label for="additionalEmployeeSearch" class="sr-only">Search employees</label>
                            <input id="additionalEmployeeSearch" type="search" placeholder="Search name or department..." autocomplete="off">
                            <div class="permission-employee-list" id="additionalEmployeeList">
                                @foreach(($roleAccounts ?? collect())->flatten(1)->filter(fn ($account) => strtolower((string) $account->status) === 'active' && strtolower((string) $account->department) !== 'admin')->sortBy('name') as $account)
                                    <button type="button" class="permission-employee-choice" data-access-employee="{{ $account->id }}" data-access-name="{{ $account->name }}" data-access-department="{{ strtolower((string) $account->department) }}" data-access-role="{{ $account->role }}">
                                        <i class="fa-solid fa-user"></i><span><strong>{{ $account->name }}</strong><small>{{ $account->department }} · {{ ucfirst((string) $account->role) }}</small></span><i class="fa-regular fa-circle"></i>
                                    </button>
                                @endforeach
                            </div>
                            <p class="permission-employee-empty" id="additionalEmployeeEmpty" hidden>No matching active employees.</p>
                        </section>
                        <div class="permission-access-details">
                            <section class="permission-access-card permission-access-selected">
                                <h2><i class="fa-solid fa-user-shield"></i> Selected Employee</h2>
                                <strong id="additionalSelectedName">Select an employee on the left</strong>
                                <p id="additionalSelectedRole">Primary role and department remain unchanged.</p>
                            </section>
                            <section class="permission-access-card">
                                <h2><span>2</span> <i class="fa-solid fa-layer-group"></i> Choose Additional Department</h2>
                                <div class="permission-department-picks" id="additionalDepartmentList">
                                    @foreach(['purchase' => 'Purchasing', 'warehouse' => 'Warehouse', 'operation' => 'Operations', 'maintenance' => 'Maintenance'] as $key => $label)
                                        <button type="button" data-access-department-choice="{{ $key }}" class="permission-department-choice" disabled><i class="fa-solid {{ ['purchase' => 'fa-cart-shopping', 'warehouse' => 'fa-box', 'operation' => 'fa-bus', 'maintenance' => 'fa-screwdriver-wrench'][$key] }}"></i> {{ $label }}</button>
                                    @endforeach
                                </div>
                            </section>
                            <section class="permission-access-card">
                                <h2><span>3</span> <i class="fa-solid fa-screwdriver-wrench"></i> Select Features / Permissions</h2>
                                <p>Only pre-defined read-only features are listed. Approval, issuance, and creation remain unavailable.</p>
                                <div class="permission-access-features" id="additionalFeatureList"><p>Select an employee and department to preview available features.</p></div>
                            </section>
                            <section class="permission-access-card">
                                <h2><span>4</span> <i class="fa-solid fa-clipboard-check"></i> Review Selection</h2>
                                <div id="additionalAccessReview">No permissions selected.</div>
                                <div class="permission-access-actions">
                                    <button type="button" id="additionalAccessClear" class="secondary-btn"><i class="fa-solid fa-rotate-left"></i> Clear Selection</button>
                                    <button type="button" class="primary-btn" disabled title="Backend feature grants and authorization tests are not implemented"><i class="fa-solid fa-lock"></i> Save Additional Access (Unavailable)</button>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>
                <div id="permissionDepartmentRoles">
                <section class="access-workspace">
                    <x-admin.role-directory
                        :role-permissions="$rolePermissions"
                        :role-accounts="$roleAccounts ?? collect()"
                        :selected-role-permission="$selectedRolePermission"
                    />

                    @if($selectedRolePermission)
                        <x-admin.role-permission-panel
                            :selected-role-permission="$selectedRolePermission"
                            :assigned-accounts="($roleAccounts ?? collect())->get($selectedRolePermission->role_key, collect())"
                            :permission-modules="$permissionModules"
                        />
                    @endif
                </section>
                </div>
            @endif
        </main>
    </div>

</x-layout.app>
