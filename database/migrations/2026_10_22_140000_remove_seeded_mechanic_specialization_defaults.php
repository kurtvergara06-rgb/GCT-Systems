<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mechanic_specialization_options')) {
            return;
        }

        // These entries came from the original predefined picker. Removing catalog
        // entries does not modify mechanics.specialization or any mechanic history.
        $defaults = [
            'Aircon', 'Bodyworks', 'Brakes', 'Diagnostics', 'Electrical', 'Engine',
            'Fuel System', 'Lubrication', 'Preventive Maintenance', 'Suspension',
            'Tires & Wheels', 'Transmission', 'Welding & Fabrication',
        ];

        DB::table('mechanic_specialization_options')
            ->whereIn('normalized_name', array_map('mb_strtolower', $defaults))
            ->delete();
    }

    public function down(): void
    {
        // Never recreate unwanted predefined entries or overwrite user-managed options.
    }
};
