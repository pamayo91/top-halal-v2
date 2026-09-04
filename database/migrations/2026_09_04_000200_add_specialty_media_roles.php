<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('media_asset_id')->nullable()->after('slug')->constrained('media_assets')->nullOnDelete();
        });

        Schema::table('restaurant_media', function (Blueprint $table): void {
            $table->string('role', 32)->default('gallery')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_media', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_asset_id');
        });
    }
};
