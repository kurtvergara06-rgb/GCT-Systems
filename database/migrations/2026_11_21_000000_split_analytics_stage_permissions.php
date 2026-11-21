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

        foreach (DB::table('role_permissions')->select('id', 'role_key', 'permissions')->get() as $role) {
            $permissions = json_decode((string) $role->permissions, true);
            if (! is_array($permissions)) {
                continue;
            }

            $analytics = (array) ($permissions['analytics'] ?? []);
            $legacyAnalyze = (bool) ($analytics['analyze'] ?? false);
            $admin = $role->role_key === 'admin_head';

            // Existing Analyze access covered both stages. Preserve that access,
            // while keeping any explicitly defined stage grants intact.
            $analytics['diagnostic'] = $admin || (bool) ($analytics['diagnostic'] ?? $legacyAnalyze);
            $analytics['predictive'] = $admin || (bool) ($analytics['predictive'] ?? $legacyAnalyze);
            // Retire the merged capability, retaining the other stored permissions.
            unset($analytics['analyze']);
            $permissions['analytics'] = $analytics;

            DB::table('role_permissions')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions),
            ]);
        }
    }

    public function down(): void
    {
        // Do not collapse independently granted permissions or revoke access.
        // Restoring a combined permission would lose stage-specific choices.
    }
};
