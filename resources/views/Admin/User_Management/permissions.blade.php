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
                subtitle="Manage department roles and system access levels"
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
                <section class="permission-additional-access" id="permissionAdditionalAccess" hidden>
                    <div class="permission-additional-icon"><i class="fa-solid fa-lock"></i></div>
                    <div>
                        <h2>Employee Additional Access</h2>
                        <p>Account-specific cross-department permissions are not yet supported. Existing department-role permissions remain enforced. No access can be granted from this screen until audited backend authorization is available.</p>
                    </div>
                    <span class="permission-additional-state">Not available yet</span>
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
