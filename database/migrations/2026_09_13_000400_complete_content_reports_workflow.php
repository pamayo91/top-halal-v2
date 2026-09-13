<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_content_reports', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('reporter_name', 100)->nullable()->after('user_id');
            $table->string('reporter_email')->nullable()->after('reporter_name');
            $table->boolean('is_authenticated')->default(false)->after('reporter_email');
            $table->string('content_title', 255)->nullable()->after('content_url');
            $table->string('status', 20)->default('new')->after('message')->index();
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->index(['content_type', 'content_id', 'status'], 'content_reports_context_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('editorial_content_reports', function (Blueprint $table): void {
            $table->dropIndex('content_reports_context_status_idx');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['reporter_name', 'reporter_email', 'is_authenticated', 'content_title', 'status', 'status_changed_at']);
        });
    }
};
