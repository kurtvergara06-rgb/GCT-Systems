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

        DB::table('role_permissions')
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                $permissions = json_decode((string) $row->permissions, true) ?: [];

                if (isset($permissions['warehouse']) && is_array($permissions['warehouse'])) {
                    unset($permissions['warehouse']['approve']);
                }

                DB::table('role_permissions')
                    ->where('id', $row->id)
                    ->update([
                        'permissions' => json_encode($permissions),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        DB::table('role_permissions')
            ->orderBy('id')
            ->get()
            ->each(function ($row): void {
                $permissions = json_decode((string) $row->permissions, true) ?: [];
                $permissions['warehouse'] ??= [];

                $permissions['warehouse']['approve'] =
                    $row->role_key === 'admin_head'
                    || (
                        strtolower((string) $row->department) === 'warehouse'
                        && strtolower((string) $row->role_type) === 'head'
                    );

                DB::table('role_permissions')
                    ->where('id', $row->id)
                    ->update([
                        'permissions' => json_encode($permissions),
                        'updated_at' => now(),
                    ]);
            });
    }
};
