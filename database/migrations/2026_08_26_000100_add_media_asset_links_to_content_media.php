<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('content_media', function (Blueprint $table): void {
            $table->dropUnique('content_media_legacy_unique');
            $table->unsignedBigInteger('legacy_attachment_id')->nullable()->change();
            $table->foreignId('media_asset_id')->nullable()->after('legacy_attachment_id')->constrained('media_assets')->nullOnDelete();
            $table->unique(['content_type', 'content_id', 'media_asset_id', 'role'], 'content_media_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::table('content_media', function (Blueprint $table): void {
            $table->dropUnique('content_media_asset_unique');
            $table->dropConstrainedForeignId('media_asset_id');
            $table->unsignedBigInteger('legacy_attachment_id')->nullable(false)->change();
            $table->unique(['content_type', 'content_id', 'legacy_attachment_id', 'role'], 'content_media_legacy_unique');
        });
    }
};
