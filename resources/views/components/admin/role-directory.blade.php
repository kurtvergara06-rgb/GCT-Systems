@props([
    'rolePermissions',
    'selectedRolePermission' => null,
])

<x-ui.ajax-region
    name="role-directory"
    id="roleDirectoryRegion"
    class="role-directory"
>
    <div class="role-directory-heading">
        <div>
            <span class="section-kicker">Roles</span>
            <h2>Role Directory</h2>
            <p>Select a role to review access.</p>
        </div>

        <span class="role-count">{{ $rolePermissions->count() }}</span>
    </div>

    <div class="role-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
            type="text"
            id="rolePermissionSearch"
            placeholder="Search role..."
            autocomplete="off"
        >
    </div>

    <div class="role-directory-list" id="roleDirectoryList">
        @foreach($rolePermissions as $rolePermission)
            <form
                method="GET"
                action="{{ route('admin.roles-permissions') }}"
                class="role-select-form"
                data-role-selector-form
            >
                <input type="hidden" name="role" value="{{ $rolePermission->role_key }}">

                <button
                    type="submit"
                    class="directory-role {{ $selectedRolePermission?->role_key === $rolePermission->role_key ? 'active' : '' }}"
                    data-role-search="{{ strtolower($rolePermission->label . ' ' . $rolePermission->department) }}"
                    data-role-key="{{ $rolePermission->role_key }}"
                >
                    <div class="directory-role-icon {{ $rolePermission->role_type === 'admin' ? 'admin' : $rolePermission->role_type }}">
                        <i class="fa-solid {{ $rolePermission->role_type === 'admin' ? 'fa-user-shield' : ($rolePermission->role_type === 'head' ? 'fa-user-tie' : 'fa-user') }}"></i>
                    </div>

                    <div class="directory-role-info">
                        <strong>{{ $rolePermission->label }}</strong>
                        <span>{{ $rolePermission->department }} Department</span>
                    </div>

                    <span class="directory-role-type {{ $rolePermission->role_type === 'admin' ? 'admin' : $rolePermission->role_type }}">
                        {{ $rolePermission->role_type === 'admin' ? 'Protected' : ucfirst($rolePermission->role_type) }}
                    </span>
                </button>
            </form>
        @endforeach
    </div>
</x-ui.ajax-region>
