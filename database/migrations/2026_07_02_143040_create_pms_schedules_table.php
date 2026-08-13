<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pms_schedules')) {
            Schema::create('pms_schedules', function (Blueprint $table) {
                $table->id();
                $table->string('bus_no');
                $table->decimal('last_pms_km', 12, 2)->default(0);
                $table->decimal('pms_interval_km', 12, 2)->default(5000);
                $table->decimal('next_pms_km', 12, 2)->default(5000);
                $table->string('maintenance_type')->default('Change Oil');
                $table->date('recommended_date')->nullable();
                $table->timestamps();

                $table->unique(
                    ['bus_no', 'maintenance_type'],
                    'pms_schedules_bus_no_maintenance_type_unique'
                );
            });
        }

        if (
            Schema::hasTable('job_orders')
            && Schema::hasColumn('job_orders', 'pms_schedule_id')
        ) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->foreign('pms_schedule_id')
                    ->references('id')
                    ->on('pms_schedules')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('job_orders')
            && Schema::hasColumn('job_orders', 'pms_schedule_id')
        ) {
            Schema::table('job_orders', function (Blueprint $table) {
                $table->dropForeign(['pms_schedule_id']);
            });
        }

        Schema::dropIfExists('pms_schedules');
    }
};
