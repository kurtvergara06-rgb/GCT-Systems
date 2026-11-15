<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->decimal('estimated_duration_value', 8, 2)
                ->nullable()
                ->after('part_needed');
            $table->string('estimated_duration_unit', 20)
                ->nullable()
                ->after('estimated_duration_value');
        });
    }

    public function down(): void
    {
        Schema::table('job_orders', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_duration_value',
                'estimated_duration_unit',
            ]);
        });
    }
};
