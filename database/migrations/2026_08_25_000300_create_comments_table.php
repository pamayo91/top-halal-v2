<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_wp_comment_id')->nullable()->unique();
            $table->unsignedBigInteger('legacy_wp_post_id')->nullable()->index();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->unsignedBigInteger('legacy_user_id')->nullable()->index();
            $table->string('author_name', 100);
            $table->string('author_email')->nullable();
            $table->text('content');
            $table->string('status', 20)->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['article_id', 'status']);
            $table->index(['page_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
