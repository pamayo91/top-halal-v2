<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->timestamp('legacy_created_at')->nullable()->after('status');
            $table->timestamp('legacy_updated_at')->nullable()->after('legacy_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn(['legacy_created_at', 'legacy_updated_at']);
        });
    }
};
