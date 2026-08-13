<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topbar_notifications')) {
            return;
        }

        Schema::table('topbar_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('topbar_notifications', 'module')) {
                $table->string('module', 50)
                    ->nullable()
                    ->index()
                    ->after('id');
            }

            if (! Schema::hasColumn('topbar_notifications', 'created_by')) {
                $table->unsignedBigInteger('created_by')
                    ->nullable()
                    ->index()
                    ->after('message');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left as a no-op. This migration repairs legacy schemas,
        // and rolling it back should not risk removing columns that may have
        // existed before this corrective migration in another environment.
    }
};
