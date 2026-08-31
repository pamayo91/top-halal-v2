<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            // Publicly proposed restaurants do not originate from WordPress.
            $table->unsignedBigInteger('legacy_wp_id')->nullable()->change();
            $table->boolean('has_halal_meat')->default(false)->after('is_claimed');
            $table->boolean('has_halal_chicken')->default(false)->after('has_halal_meat');
        });

        Schema::table('restaurant_opening_hours', function (Blueprint $table): void {
            $table->unsignedTinyInteger('slot')->default(1)->after('day');
            $table->boolean('is_open_24_hours')->default(false)->after('is_closed');
        });

        Schema::table('restaurant_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_attachment_id')->nullable()->change();
            $table->foreignId('media_asset_id')->nullable()->after('legacy_attachment_id')->constrained('media_assets')->nullOnDelete();
            $table->unique(['restaurant_id', 'media_asset_id'], 'restaurant_media_asset_unique');
        });

        // Preserve every existing legacy relation while moving lookups to the direct V2 asset key.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::table('restaurant_media')->whereNull('media_asset_id')->orderBy('id')->each(function (object $media): void {
                $assetId = DB::table('media_assets')->where('legacy_attachment_id', $media->legacy_attachment_id)->value('id');
                if ($assetId !== null) DB::table('restaurant_media')->where('id', $media->id)->update(['media_asset_id' => $assetId]);
            });
        } else {
            DB::table('restaurant_media as restaurant_media')
                ->join('media_assets as media_assets', 'media_assets.legacy_attachment_id', '=', 'restaurant_media.legacy_attachment_id')
                ->whereNull('restaurant_media.media_asset_id')
                ->update(['restaurant_media.media_asset_id' => DB::raw('media_assets.id')]);
        }

        Schema::create('restaurant_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('submitter_email');
            $table->string('submitter_role', 20);
            $table->char('ip_hash', 64)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['submitter_role', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_submissions');

        Schema::table('restaurant_media', function (Blueprint $table): void {
            $table->dropUnique('restaurant_media_asset_unique');
            $table->dropConstrainedForeignId('media_asset_id');
            $table->unsignedBigInteger('legacy_attachment_id')->nullable(false)->change();
        });

        Schema::table('restaurant_opening_hours', function (Blueprint $table): void {
            $table->dropColumn(['slot', 'is_open_24_hours']);
        });

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropColumn(['has_halal_meat', 'has_halal_chicken']);
            $table->unsignedBigInteger('legacy_wp_id')->nullable(false)->change();
        });
    }
};
