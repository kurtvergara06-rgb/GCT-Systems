<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_processed_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_upload_id')
                ->constrained('batch_uploads')
                ->cascadeOnDelete();
            $table->json('payload');
            $table->json('raw_data')->nullable();
            $table->string('status')->default('In Review');
            $table->string('destination_type')->nullable();
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['batch_upload_id', 'status']);
            $table->index(['destination_type', 'destination_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_processed_records');
    }
};
