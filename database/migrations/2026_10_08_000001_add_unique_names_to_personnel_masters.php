<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->unique('driver_name', 'drivers_driver_name_unique');
        });

        Schema::table('mechanics', function (Blueprint $table): void {
            $table->unique('mechanic_name', 'mechanics_mechanic_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table): void {
            $table->dropUnique('drivers_driver_name_unique');
        });

        Schema::table('mechanics', function (Blueprint $table): void {
            $table->dropUnique('mechanics_mechanic_name_unique');
        });
    }
};
