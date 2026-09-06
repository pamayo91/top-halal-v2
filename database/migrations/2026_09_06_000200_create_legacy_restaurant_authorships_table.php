<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_restaurant_authorships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('legacy_wp_user_id')->index();
            $table->unsignedBigInteger('legacy_wp_id')->unique();
            $table->string('source_post_status', 20);
            $table->uuid('import_batch')->nullable()->index();
            $table->timestamps();
            $table->unique(['user_id', 'restaurant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_restaurant_authorships');
    }
};
