<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->text('purpose')->nullable();
            $table->date('target_date')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropColumn(['purpose', 'target_date']);
        });
    }
};
