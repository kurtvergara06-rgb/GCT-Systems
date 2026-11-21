<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_service_heartbeats', function (Blueprint $table) {
            $table->string('service', 50)->primary();
            $table->unsignedBigInteger('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_service_heartbeats');
    }
};
