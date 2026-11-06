<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_clients', function (Blueprint $table): void {
            // Nullable to preserve historical and Client Trip Times-created clients.
            $table->string('company_address', 500)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('contact_position', 150)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('contact_email', 254)->nullable();
            $table->date('service_start_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('operation_clients', function (Blueprint $table): void {
            $table->dropColumn([
                'company_address',
                'contact_person',
                'contact_position',
                'contact_number',
                'contact_email',
                'service_start_date',
            ]);
        });
    }
};
