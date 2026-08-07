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
            if (Schema::hasColumn('topbar_notifications', 'user_id')) {
                $table->unsignedBigInteger('user_id')
                    ->nullable()
                    ->change();
            }

            if (Schema::hasColumn('topbar_notifications', 'title')) {
                $table->string('title')
                    ->nullable()
                    ->change();
            }
        });
    }

    public function down(): void
    {
        // No-op by design. These columns belong to a legacy notification schema,
        // and restoring NOT NULL constraints could break records created by the
        // current topbar notification implementation.
    }
};
