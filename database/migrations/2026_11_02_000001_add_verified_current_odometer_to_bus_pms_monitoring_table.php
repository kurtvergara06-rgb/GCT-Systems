<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bus_pms_monitoring', function (Blueprint $table) {
            $table->decimal('verified_current_odometer_km', 12, 2)->nullable();
            $table->timestamp('current_odometer_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bus_pms_monitoring', function (Blueprint $table) {
            $table->dropColumn(['verified_current_odometer_km', 'current_odometer_verified_at']);
        });
    }
};
