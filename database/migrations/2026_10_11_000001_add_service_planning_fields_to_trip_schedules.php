<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table): void {
            $table->string('direction', 20)->nullable()->after('shuttle_route_id');
            $table->unsignedSmallInteger('required_buses')->default(1)->after('direction');
        });
    }

    public function down(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table): void {
            $table->dropColumn(['direction', 'required_buses']);
        });
    }
};
