<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->json('restock_items')->nullable();
            $table->string('restock_signature_path')->nullable();
            $table->text('restock_approval_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropColumn(['restock_items', 'restock_signature_path', 'restock_approval_notes']);
        });
    }
};
