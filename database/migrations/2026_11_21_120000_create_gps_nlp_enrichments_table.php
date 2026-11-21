<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_nlp_enrichments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gps_trip_record_id')->unique()->constrained('gps_trip_records')->cascadeOnDelete();
            $table->foreignId('batch_upload_id')->index()->constrained('batch_uploads')->cascadeOnDelete();
            $table->string('source', 64)->default('operational_rules');
            $table->json('entities')->nullable();
            $table->json('severity_suggestion')->nullable();
            $table->json('anomaly_suggestion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_nlp_enrichments');
    }
};
