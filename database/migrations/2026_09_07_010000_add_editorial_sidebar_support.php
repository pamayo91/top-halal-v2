<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['articles', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('editorial_sidebar_enabled')->nullable()->after('content_html');
                $table->json('editorial_sidebar_overrides')->nullable()->after('editorial_sidebar_enabled');
            });
        }

        Schema::create('editorial_content_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('content_type', 20);
            $table->unsignedBigInteger('content_id');
            $table->string('content_url', 2048);
            $table->text('message');
            $table->char('ip_hash', 64);
            $table->timestamps();
            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_content_reports');
        foreach (['articles', 'pages'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['editorial_sidebar_enabled', 'editorial_sidebar_overrides']));
        }
    }
};
