<x-layout.app
    title="FROMS - Admin Dashboard"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Admin/admin-dashboard.css'
    ]"
>
    @php
        $authUser = auth()->user();

        $sidebarName = $authUser?->name ?? 'System Admin';

        $department = trim($authUser?->department ?? 'Admin');
        $role = strtolower(trim($authUser?->role ?? 'head'));

        if (strtolower($department) === 'admin') {
            $sidebarRole = $role === 'head'
                ? 'System Admin'
                : 'Admin Staff';
        } else {
            $sidebarRole = $department . ' ' . ucfirst($role ?: 'Staff');
        }
    @endphp

    <div class="app">

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

        <main class="main">

            <x-layout.topbar
                title="Admin Dashboard"
                subtitle="Manage system access, departments, and overall records"
                notification-count="6"
            />

            <section class="admin-stats-grid">

                <div class="admin-card">
                    <div class="admin-card-icon blue">
                        <i class="fa-solid fa-truck"></i>
                    </div>

                    <div>
                        <p>Maintenance</p>
                        <h2>Active</h2>
                        <span>Job orders and repair monitoring</span>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-icon green">
                        <i class="fa-solid fa-warehouse"></i>
                    </div>

                    <div>
                        <p>Warehouse</p>
                        <h2>Active</h2>
                        <span>Inventory and part requests</span>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-icon orange">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>

                    <div>
                        <p>Purchase</p>
                        <h2>Active</h2>
                        <span>Purchase orders and supplier records</span>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-card-icon purple">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>

                    <div>
                        <p>Operations</p>
                        <h2>Active</h2>
                        <span>Attendance and operational monitoring</span>
                    </div>
                </div>

            </section>

            <section class="admin-section">

                <div class="section-header">
                    <div>
                        <h2>Department Access</h2>
                        <p>Open and monitor each department module from one admin page.</p>
                    </div>
                </div>

                <div class="department-grid">

                    <a href="{{ route('maintenance-dashboard') }}" class="department-card">
                        <div class="department-icon blue">
                            <i class="fa-solid fa-truck"></i>
                        </div>

                        <div>
                            <h3>Maintenance Department</h3>
                            <p>Manage job orders, mechanics, PMS schedules, fuel reports, and purchase requests.</p>
                        </div>

                        <span>
                            Open
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </a>

                    <a href="{{ route('inventory') }}" class="department-card">
                        <div class="department-icon green">
                            <i class="fa-solid fa-warehouse"></i>
                        </div>

                        <div>
                            <h3>Warehouse Department</h3>
                            <p>Monitor inventory, stock levels, reorder points, and part requests.</p>
                        </div>

                        <span>
                            Open
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </a>

                    <a href="{{ route('purchase-orders') }}" class="department-card">
                        <div class="department-icon orange">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </div>

                        <div>
                            <h3>Purchase Department</h3>
                            <p>Manage requested purchases, purchase orders, deliveries, and supplier transactions.</p>
                        </div>

                        <span>
                            Open
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </a>

                    <a href="{{ route('dashboard-operation') }}" class="department-card">
                        <div class="department-icon purple">
                            <i class="fa-solid fa-users-gear"></i>
                        </div>

                        <div>
                            <h3>Operations Department</h3>
                            <p>Track driver attendance, mechanic attendance, and available personnel.</p>
                        </div>

                        <span>
                            Open
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </a>

                    <a href="{{ route('batch-file-processing') }}" class="department-card">
                        <div class="department-icon blue">
                            <i class="fa-solid fa-file-arrow-up"></i>
                        </div>

                        <div>
                            <h3>Batch File Processing</h3>
                            <p>Upload GPS files, extract trip data using NLP, and generate structured records.</p>
                        </div>

                        <span>
                            Open
                            <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </a>

                </div>

            </section>

            <section class="admin-section">

                <div class="section-header">
                    <div>
                        <h2>User Management</h2>
                        <p>Sample admin table for system users and department access.</p>
                    </div>

                    <button type="button" class="admin-add-btn">
                        <i class="fa-solid fa-plus"></i>
                        New User
                    </button>
                </div>

                <div class="admin-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Access</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">A</div>
                                        <div>
                                            <strong>System Admin</strong>
                                            <small>admin@gct-system.test</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Admin</td>
                                <td>Administrator</td>
                                <td>
                                    <span class="status-badge active">Active</span>
                                </td>
                                <td>
                                    <span class="access-badge full">Full Access</span>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">M</div>
                                        <div>
                                            <strong>R. Lim</strong>
                                            <small>maintenance@gct-system.test</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Maintenance</td>
                                <td>Maintenance Admin</td>
                                <td>
                                    <span class="status-badge active">Active</span>
                                </td>
                                <td>
                                    <span class="access-badge limited">Department Access</span>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">W</div>
                                        <div>
                                            <strong>Warehouse Staff</strong>
                                            <small>warehouse@gct-system.test</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Warehouse</td>
                                <td>Staff</td>
                                <td>
                                    <span class="status-badge active">Active</span>
                                </td>
                                <td>
                                    <span class="access-badge limited">Department Access</span>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">P</div>
                                        <div>
                                            <strong>Purchase Staff</strong>
                                            <small>purchase@gct-system.test</small>
                                        </div>
                                    </div>
                                </td>
                                <td>Purchase</td>
                                <td>Staff</td>
                                <td>
                                    <span class="status-badge active">Active</span>
                                </td>
                                <td>
                                    <span class="access-badge limited">Department Access</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </section>

        </main>
    </div>
</x-layout.app>