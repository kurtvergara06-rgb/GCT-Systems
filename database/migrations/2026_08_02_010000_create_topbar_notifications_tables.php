<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('topbar_notifications')) {
            Schema::create('topbar_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('module', 50)->index();
                $table->string('entity', 80)->index();
                $table->string('action', 50);
                $table->string('record_id')->nullable();
                $table->text('message');
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('topbar_read_states')) {
            Schema::create('topbar_read_states', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->timestamp('notifications_read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('topbar_read_states');
        Schema::dropIfExists('topbar_notifications');
    }
};
