<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->char('fingerprint', 64)->nullable()->unique()->after('ip_hash');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropUnique(['fingerprint']);
            $table->dropColumn('fingerprint');
        });
    }
};
