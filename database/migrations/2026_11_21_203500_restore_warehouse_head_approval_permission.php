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

        // A previous migration removed the warehouse approval flag from every role.
        // Restore it only for the Head roles that authorize purchase forwarding
        // and signed restocking; never grant it to staff or unrelated departments.
        foreach (['warehouse_head', 'admin_head'] as $roleKey) {
            $row = DB::table('role_permissions')->where('role_key', $roleKey)->first();
            if (! $row) {
                continue;
            }

            $permissions = json_decode((string) $row->permissions, true) ?: [];
            $permissions['warehouse'] ??= [];
            if (array_key_exists('approve', $permissions['warehouse'])) {
                continue;
            }

            $permissions['warehouse']['approve'] = true;
            DB::table('role_permissions')->where('id', $row->id)->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Avoid silently revoking subsequently configured role approvals.
    }
};
