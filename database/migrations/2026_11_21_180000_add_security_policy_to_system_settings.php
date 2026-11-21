<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table): void {
            $table->json('security_policy')->nullable();
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_changed_at')->nullable();
        });
        DB::table('users')->whereNull('password_changed_at')->update(['password_changed_at' => now()]);
    }
    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_changed_at'));
        Schema::table('system_settings', fn (Blueprint $table) => $table->dropColumn('security_policy'));
    }
};
