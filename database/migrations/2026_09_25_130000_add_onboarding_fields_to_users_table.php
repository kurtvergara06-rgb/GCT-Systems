<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('onboarding_completed')->default(true)->after('must_change_password');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_completed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed', 'onboarding_completed_at']);
        });
    }
};
