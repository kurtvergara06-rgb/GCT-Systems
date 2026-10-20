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
            @endif
        </main>
    </div>

</x-layout.app>
