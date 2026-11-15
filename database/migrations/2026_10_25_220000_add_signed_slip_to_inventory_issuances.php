<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_issuances', function (Blueprint $table) {
            $table->string('signed_slip_path')->nullable();
            $table->string('received_by')->nullable();
            $table->timestamp('handover_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_issuances', function (Blueprint $table) {
            $table->dropColumn(['signed_slip_path', 'received_by', 'handover_confirmed_at']);
        });
    }
};
