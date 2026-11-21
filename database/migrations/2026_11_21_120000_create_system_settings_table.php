<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('organization_name', 150);
            $table->string('organization_email', 254)->nullable();
            $table->string('organization_phone', 50)->nullable();
            $table->text('organization_address')->nullable();
            $table->string('updated_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
