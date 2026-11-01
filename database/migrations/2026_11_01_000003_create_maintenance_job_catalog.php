<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('maintenance_job_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->json('specializations');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $jobs = [
            'Change Oil' => ['Change Oil', 'Lubrication', 'Preventive Maintenance'],
            'Oil Filter Replacement' => ['Oil Filter Replacement', 'Lubrication', 'Preventive Maintenance'],
            'Brake Inspection' => ['Brakes'],
            'Brake Pad Replacement' => ['Brakes'],
            'Air Filter Replacement' => ['Engine', 'Preventive Maintenance'],
            'Aircon Servicing' => ['Aircon'],
            'Battery Replacement' => ['Electrical'],
            'Tire Replacement' => ['Tires & Wheels'],
            'Engine Tune-Up' => ['Engine', 'Diagnostics'],
            'Full PMS' => ['Preventive Maintenance'],
            'Brake Adjustment' => ['Brakes'],
            'Brake Air System Inspection' => ['Brakes'],
            'Brake Lining Replacement' => ['Brakes'],
            'Engine Oil Leak Inspection' => ['Engine', 'Diagnostics'],
            'Fuel Filter Replacement' => ['Fuel System', 'Preventive Maintenance'],
            'Cooling System Inspection' => ['Engine', 'Diagnostics'],
            'Radiator and Coolant Servicing' => ['Engine', 'Preventive Maintenance'],
            'Transmission and Clutch Inspection' => ['Transmission'],
            'Suspension Inspection and Repair' => ['Suspension'],
            'Steering System Inspection' => ['Suspension'],
            'Wheel Bearing Inspection' => ['Tires & Wheels'],
            'Tire Pressure and Condition Check' => ['Tires & Wheels'],
            'Headlight and Signal Light Repair' => ['Electrical'],
            'Wiring and Electrical Troubleshooting' => ['Electrical', 'Diagnostics'],
            'Aircon Refrigerant Recharge' => ['Aircon'],
            'Body Panel and Bumper Repair' => ['Bodyworks'],
            'Door and Window Mechanism Repair' => ['Bodyworks'],
            'Chassis Bolt Retightening' => ['Preventive Maintenance'],
            'Chassis Greasing' => ['Lubrication', 'Preventive Maintenance'],
            'Exhaust System Inspection and Repair' => ['Engine'],
        ];
        $now = now();
        foreach ($jobs as $name => $skills) {
            DB::table('maintenance_job_catalog')->insert([
                'name' => $name, 'specializations' => json_encode($skills),
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
    public function down(): void {
        Schema::dropIfExists('maintenance_job_catalog');
    }
};
