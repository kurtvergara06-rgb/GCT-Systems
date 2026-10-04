<?php

namespace App\Services;

use App\Models\Admin\RolePermission;
use Illuminate\Support\Facades\Schema;

class RolePermissionService
{
    public function roleModuleKey(RolePermission $rolePermission): ?string
    {
        if ($rolePermission->role_key === 'admin_head') {
            return null;
        }

        $moduleKey = strtolower(trim((string) $rolePermission->department));

        return array_key_exists($moduleKey, $this->modules())
            ? $moduleKey
            : null;
    }

    public function isolatedPermissions(RolePermission $rolePermission, array $submittedPermissions): array
    {
        $normalized = [];

        foreach ($this->modules() as $moduleKey => $module) {
            foreach (array_keys($module['capabilities']) as $capabilityKey) {
                $normalized[$moduleKey][$capabilityKey] = false;
            }
        }

        if ($rolePermission->role_key === 'admin_head') {
            foreach ($normalized as $moduleKey => $capabilities) {
                foreach (array_keys($capabilities) as $capabilityKey) {
                    $normalized[$moduleKey][$capabilityKey] = true;
                }
            }

            return $normalized;
        }

        $roleModuleKey = $this->roleModuleKey($rolePermission);

        if ($roleModuleKey === null) {
            return $normalized;
        }

        foreach (array_keys($this->modules()[$roleModuleKey]['capabilities']) as $capabilityKey) {
            $normalized[$roleModuleKey][$capabilityKey] = (bool) data_get(
                $submittedPermissions,
                $roleModuleKey.'.'.$capabilityKey,
                false
            );
        }

        return $normalized;
    }

    public function data(?string $selectedRoleKey = null): array
    {
        if (! Schema::hasTable('role_permissions')) {
            return [
                'rolePermissions' => collect(),
                'selectedRolePermission' => null,
                'permissionModules' => $this->modules(),
                'permissionsReady' => false,
            ];
        }

        $roles = RolePermission::query()
            ->orderByRaw("CASE WHEN role_key = 'admin_head' THEN 0 ELSE 1 END")
            ->orderBy('department')
            ->orderByRaw("CASE WHEN role_type = 'head' THEN 0 ELSE 1 END")
            ->get();

        $selected = $roles->firstWhere('role_key', $selectedRoleKey)
            ?? $roles->first();

        return [
            'rolePermissions' => $roles,
            'selectedRolePermission' => $selected,
            'permissionModules' => $this->modules(),
            'permissionsReady' => true,
        ];
    }

    public function modules(): array
    {
        return [
            'operation' => [
                'label' => 'Operation',
                'icon' => 'fa-bus',
                'description' => 'Shuttle buses, routes, schedules, attendance, and trip records',
                'capabilities' => [
                    'view' => ['label' => 'View', 'icon' => 'fa-eye'],
                    'edit' => ['label' => 'Create / Edit', 'icon' => 'fa-pen'],
                    'approve' => ['label' => 'Approve', 'icon' => 'fa-check-double'],
                ],
            ],
            'maintenance' => [
                'label' => 'Maintenance',
                'icon' => 'fa-screwdriver-wrench',
                'description' => 'Job orders, PMS scheduling, mechanics, and fuel reports',
                'capabilities' => [
                    'view' => ['label' => 'View', 'icon' => 'fa-eye'],
                    'edit' => ['label' => 'Create / Edit', 'icon' => 'fa-pen'],
                    'approve' => ['label' => 'Approve', 'icon' => 'fa-check-double'],
                ],
            ],
            'purchase' => [
                'label' => 'Purchase',
                'icon' => 'fa-cart-shopping',
                'description' => 'Purchase requests, purchase orders, and scheduled purchases',
                'capabilities' => [
                    'view' => ['label' => 'View', 'icon' => 'fa-eye'],
                    'edit' => ['label' => 'Create / Edit', 'icon' => 'fa-pen'],
                    'approve' => ['label' => 'Approve', 'icon' => 'fa-check-double'],
                ],
            ],
            'warehouse' => [
                'label' => 'Warehouse',
                'icon' => 'fa-boxes-stacked',
                'description' => 'Inventory, part requests, deliveries, and stock movements',
                'capabilities' => [
                    'view' => ['label' => 'View', 'icon' => 'fa-eye'],
                    'edit' => ['label' => 'Create / Edit', 'icon' => 'fa-pen'],
                    'approve' => ['label' => 'Approve', 'icon' => 'fa-check-double'],
                ],
            ],
            'analytics' => [
                'label' => 'Analytics',
                'icon' => 'fa-chart-line',
                'description' => 'Operational analytics and decision-support recommendations',
                'capabilities' => [
                    'view' => ['label' => 'View', 'icon' => 'fa-eye'],
                    'analyze' => ['label' => 'Analyze', 'icon' => 'fa-chart-column'],
                    'recommendations' => ['label' => 'Recommendations', 'icon' => 'fa-lightbulb'],
                ],
            ],
            'administration' => [
                'label' => 'Administration',
                'icon' => 'fa-user-shield',
                'description' => 'Users, permissions, monitoring, data management, and settings',
                'capabilities' => [
                    'view' => ['label' => 'View', 'icon' => 'fa-eye'],
                    'manage' => ['label' => 'Manage', 'icon' => 'fa-user-gear'],
                    'full_control' => ['label' => 'Full Control', 'icon' => 'fa-shield-halved'],
                ],
            ],
        ];
    }
}
