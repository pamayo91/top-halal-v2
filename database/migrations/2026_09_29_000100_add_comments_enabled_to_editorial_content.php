<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['articles', 'pages'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->boolean('comments_enabled')->default(true)->after('content_html'));
        }
    }

    public function down(): void
    {
        foreach (['articles', 'pages'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('comments_enabled'));
        }
    }
};
