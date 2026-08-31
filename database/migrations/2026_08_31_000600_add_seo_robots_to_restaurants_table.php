<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->string('seo_robots', 255)->nullable()->after('seo_description');
            $table->integer('seo_max_snippet')->nullable()->after('seo_robots');
            $table->string('seo_max_image_preview', 16)->nullable()->after('seo_max_snippet');
            $table->integer('seo_max_video_preview')->nullable()->after('seo_max_image_preview');
            $table->timestamp('seo_unavailable_after')->nullable()->after('seo_max_video_preview');
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn([
                'seo_robots',
                'seo_max_snippet',
                'seo_max_image_preview',
                'seo_max_video_preview',
                'seo_unavailable_after',
            ]);
        });
    }
};
