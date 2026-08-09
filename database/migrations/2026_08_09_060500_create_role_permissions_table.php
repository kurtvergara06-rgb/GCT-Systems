<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('role_key')->unique();
            $table->string('label');
            $table->string('department');
            $table->string('role_type');
            $table->json('permissions');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $full = [
            'operation' => ['view' => true, 'edit' => true, 'approve' => true],
            'maintenance' => ['view' => true, 'edit' => true, 'approve' => true],
            'purchase' => ['view' => true, 'edit' => true, 'approve' => true],
            'warehouse' => ['view' => true, 'edit' => true, 'approve' => true],
            'analytics' => ['view' => true, 'analyze' => true, 'recommendations' => true],
            'administration' => ['view' => true, 'manage' => true, 'full_control' => true],
        ];

        $restricted = [
            'operation' => ['view' => false, 'edit' => false, 'approve' => false],
            'maintenance' => ['view' => false, 'edit' => false, 'approve' => false],
            'purchase' => ['view' => false, 'edit' => false, 'approve' => false],
            'warehouse' => ['view' => false, 'edit' => false, 'approve' => false],
            'analytics' => ['view' => false, 'analyze' => false, 'recommendations' => false],
            'administration' => ['view' => false, 'manage' => false, 'full_control' => false],
        ];

        $rows = [
            ['admin_head', 'System Admin', 'Admin', 'admin', $full],
        ];

        foreach (['Operation', 'Maintenance', 'Purchase', 'Warehouse'] as $department) {
            $module = strtolower($department);

            $headPermissions = $restricted;
            $headPermissions[$module] = ['view' => true, 'edit' => true, 'approve' => true];
            $headPermissions['analytics'] = ['view' => true, 'analyze' => true, 'recommendations' => false];

            $staffPermissions = $restricted;
            $staffPermissions[$module] = ['view' => true, 'edit' => true, 'approve' => false];

            $rows[] = [strtolower($department) . '_head', $department . ' Head', $department, 'head', $headPermissions];
            $rows[] = [strtolower($department) . '_staff', $department . ' Staff', $department, 'staff', $staffPermissions];
        }

        foreach ($rows as [$key, $label, $department, $roleType, $permissions]) {
            DB::table('role_permissions')->insert([
                'role_key' => $key,
                'label' => $label,
                'department' => $department,
                'role_type' => $roleType,
                'permissions' => json_encode($permissions),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
