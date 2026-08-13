<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('topbar_notification_reads')) {
            return;
        }

        Schema::create('topbar_notification_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('notification_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notification_id'], 'topbar_notification_reads_user_notification_unique');
            $table->index(['user_id', 'read_at'], 'topbar_notification_reads_user_read_index');
            $table->index('notification_id', 'topbar_notification_reads_notification_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topbar_notification_reads');
    }
};
