<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table): void {
            // Nullable so existing historical schedules remain valid.
            $table->string('client_name', 255)->nullable()->after('shuttle_route_id');
        });
    }

    public function down(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table): void {
            $table->dropColumn('client_name');
        });
    }
};
