<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_responses', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('incident_id')
                ->constrained('incidents')
                ->cascadeOnDelete();

            $table->string('status', 30);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('responded_by')->nullable()->index();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['incident_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_responses');
    }
};
