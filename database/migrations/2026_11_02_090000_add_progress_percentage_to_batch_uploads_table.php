<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('batch_uploads', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percentage')->default(0);
        });
    }
    public function down(): void {
        Schema::table('batch_uploads', function (Blueprint $table) {
            $table->dropColumn('progress_percentage');
        });
    }
};
