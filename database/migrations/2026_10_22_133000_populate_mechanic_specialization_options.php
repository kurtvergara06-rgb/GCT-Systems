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

        $defaultNames = [
            'Aircon', 'Bodyworks', 'Brakes', 'Diagnostics', 'Electrical', 'Engine',
            'Fuel System', 'Lubrication', 'Preventive Maintenance', 'Suspension',
            'Tires & Wheels', 'Transmission', 'Welding & Fabrication',
        ];

        $recorded = DB::table('mechanics')->whereNotNull('specialization')
            ->pluck('specialization')
            ->flatMap(fn ($value) => preg_split('/[,;]+/u', (string) $value) ?: [])
            ->map(fn ($name) => trim(preg_replace('/\s+/u', ' ', (string) $name)))
            ->filter()
            ->all();

        foreach (array_merge($defaultNames, $recorded) as $name) {
            $normalized = mb_strtolower(trim($name));
            if ($normalized === '') continue;
            DB::table('mechanic_specialization_options')->updateOrInsert(
                ['normalized_name' => $normalized],
                ['name' => trim($name), 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        // Intentionally retain the catalog and all custom/previously recorded skills.
    }
};
