<x-layout.app
  title="FROMS - Permissions"
  :assets="[
    'resources/css/Main-style/main.css',
    'resources/css/Main-style/sidebar.css',
    'resources/css/Admin/permissions.css'
  ]"
>
  <div class="app admin-permission-app">

    <x-layout.sidebar
      department="Admin"
      subtitle="System Management"
      icon="fa-shield-halved"
      user-name="S. Admin"
      user-role="System Admin"
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

    <main class="main permission-main">

      <x-layout.topbar
        title="Permissions"
        subtitle="Configure role-based access control for all departments"
        notification-count="6"
      />

      <section class="permission-card">

        <div class="permission-card-header">
          <div>
            <h2>Role Permission Matrix</h2>
            <p>Static permission overview for System Admin, department heads, and staff roles.</p>
          </div>

          <div class="permission-legend">
            <span class="legend-item">
              <i class="fa-solid fa-check"></i>
              Allowed
            </span>

            <span class="legend-item denied">
              <i class="fa-solid fa-xmark"></i>
              Denied
            </span>
          </div>
        </div>

        <div class="permission-table-wrap">

          <table class="permission-table">

            <thead>
              <tr class="module-row">
                <th rowspan="2" class="role-header">Role</th>

                <th colspan="3" class="module-header shuttle">Shuttle Management</th>
                <th colspan="3" class="module-header maintenance">Maintenance Management</th>
                <th colspan="3" class="module-header driver">Driver Assignment</th>
                <th colspan="3" class="module-header purchasing">Purchasing</th>
                <th colspan="3" class="module-header warehouse">Warehouse</th>
              </tr>

              <tr class="permission-action-row">
                <th>View</th>
                <th>Create/Edit</th>
                <th>Approve</th>

                <th>View</th>
                <th>Create/Edit</th>
                <th>Approve</th>

                <th>View</th>
                <th>Create/Edit</th>
                <th>Approve</th>

                <th>View</th>
                <th>Create/Edit</th>
                <th>Approve</th>

                <th>View</th>
                <th>Create/Edit</th>
                <th>Approve</th>
              </tr>
            </thead>

            <tbody>

              {{-- System Admin --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge system">System Admin</span>
                </td>

                @for($i = 0; $i < 15; $i++)
                  <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                @endfor
              </tr>

              {{-- Operation Head --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge operation">Operation Head</span>
                </td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
              </tr>

              {{-- Operation Staff --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge operation-staff">Operation Staff</span>
                </td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
              </tr>

              {{-- Maintenance Head --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge maintenance-head">Maintenance Head</span>
                </td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
              </tr>

              {{-- Maintenance Staff --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge maintenance-staff">Maintenance Staff</span>
                </td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
              </tr>

              {{-- Purchasing Head --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge purchasing-head">Purchasing Head</span>
                </td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
              </tr>

              {{-- Purchasing Staff --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge purchasing-staff">Purchasing Staff</span>
                </td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
              </tr>

              {{-- Warehouse Staff --}}
              <tr>
                <td class="role-cell">
                  <span class="role-badge warehouse-staff">Warehouse Staff</span>
                </td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>

                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-check"><i class="fa-solid fa-check"></i></span></td>
                <td><span class="permission-deny"><i class="fa-solid fa-xmark"></i></span></td>
              </tr>

            </tbody>
          </table>

        </div>

      </section>

      <footer class="admin-footer">
        © 2026 FROMS. All rights reserved.
      </footer>

    </main>
  </div>
</x-layout.app>