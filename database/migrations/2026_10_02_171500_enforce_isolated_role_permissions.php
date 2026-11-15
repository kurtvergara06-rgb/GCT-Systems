<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        $moduleCapabilities = [
            'operation' => ['view', 'edit', 'approve'],
            'maintenance' => ['view', 'edit', 'approve'],
            'purchase' => ['view', 'edit', 'approve'],
            'warehouse' => ['view', 'edit', 'approve'],
            'analytics' => ['view', 'analyze', 'recommendations'],
            'administration' => ['view', 'manage', 'full_control'],
        ];

        $rows = DB::table('role_permissions')->get();

        foreach ($rows as $row) {
            $existing = json_decode((string) $row->permissions, true) ?: [];
            $normalized = [];

            foreach ($moduleCapabilities as $moduleKey => $capabilities) {
                foreach ($capabilities as $capability) {
                    $normalized[$moduleKey][$capability] = false;
                }
            }

            if ($row->role_key === 'admin_head') {
                foreach ($moduleCapabilities as $moduleKey => $capabilities) {
                    foreach ($capabilities as $capability) {
                        $normalized[$moduleKey][$capability] = true;
                    }
                }
            } else {
                $roleModule = strtolower(trim((string) $row->department));

                if (array_key_exists($roleModule, $moduleCapabilities)) {
                    foreach ($moduleCapabilities[$roleModule] as $capability) {
                        $normalized[$roleModule][$capability] = (bool) data_get(
                            $existing,
                            $roleModule.'.'.$capability,
                            false
                        );
                    }
                }
            }

            DB::table('role_permissions')
                ->where('id', $row->id)
                ->update([
                    'permissions' => json_encode($normalized),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Isolation is an authorization policy correction and should not
        // restore cross-module permissions automatically.
    }
};
