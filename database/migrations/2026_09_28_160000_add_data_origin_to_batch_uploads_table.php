<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_uploads', function (Blueprint $table) {
            // Fail closed for historical rows. Existing data remains unknown
            // until an authorized reviewer explicitly verifies its provenance.
            $table->string('data_origin', 20)
                ->default('unknown')
                ->after('data_type')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('batch_uploads', function (Blueprint $table) {
            $table->dropIndex(['data_origin']);
            $table->dropColumn('data_origin');
        });
    }
};
