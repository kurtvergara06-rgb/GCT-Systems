<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shuttle_routes', function (Blueprint $table): void {
            // Legacy routes remain nullable until client allocations have been reviewed.
            $table->foreignId('operation_client_id')->nullable()->constrained('operation_clients')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shuttle_routes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operation_client_id');
        });
    }
};
