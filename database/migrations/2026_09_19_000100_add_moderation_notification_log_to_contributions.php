<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurant_reviews', function (Blueprint $table): void {
            $table->foreignId('moderation_notification_log_id')->nullable()->unique()->constrained('email_delivery_logs')->nullOnDelete();
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->foreignId('moderation_notification_log_id')->nullable()->unique()->constrained('email_delivery_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropUnique(['moderation_notification_log_id']);
            $table->dropForeign(['moderation_notification_log_id']);
            $table->dropColumn('moderation_notification_log_id');
        });
        Schema::table('restaurant_reviews', function (Blueprint $table): void {
            $table->dropUnique(['moderation_notification_log_id']);
            $table->dropForeign(['moderation_notification_log_id']);
            $table->dropColumn('moderation_notification_log_id');
        });
    }
};
