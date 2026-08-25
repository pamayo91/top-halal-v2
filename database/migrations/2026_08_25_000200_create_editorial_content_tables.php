<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  foreach (['articles','pages'] as $table) Schema::create($table, function(Blueprint $t): void {$t->id();$t->unsignedBigInteger('legacy_wp_id')->unique();$t->unsignedBigInteger('legacy_author_id')->nullable()->index();$t->string('original_title');$t->string('title');$t->string('slug')->unique();$t->longText('content_html')->nullable();$t->string('status',20)->index();$t->string('legacy_url');$t->string('seo_title')->nullable();$t->text('seo_description')->nullable();$t->string('seo_canonical')->nullable();$t->string('seo_robots')->nullable();$t->unsignedBigInteger('legacy_primary_category_id')->nullable();$t->timestamp('legacy_published_at')->nullable();$t->timestamp('legacy_modified_at')->nullable();$t->timestamps();});
  Schema::create('editorial_categories', function(Blueprint $t): void {$t->id();$t->unsignedBigInteger('legacy_term_id')->unique();$t->string('name');$t->string('slug')->unique();$t->timestamps();});
  Schema::create('editorial_tags', function(Blueprint $t): void {$t->id();$t->unsignedBigInteger('legacy_term_id')->unique();$t->string('name');$t->string('slug')->unique();$t->timestamps();});
  Schema::create('article_category', function(Blueprint $t): void {$t->foreignId('article_id')->constrained()->cascadeOnDelete();$t->foreignId('editorial_category_id')->constrained()->cascadeOnDelete();$t->primary(['article_id','editorial_category_id']);});
  Schema::create('article_tag', function(Blueprint $t): void {$t->foreignId('article_id')->constrained()->cascadeOnDelete();$t->foreignId('editorial_tag_id')->constrained()->cascadeOnDelete();$t->primary(['article_id','editorial_tag_id']);});
  Schema::create('content_media', function(Blueprint $t): void {$t->id();$t->string('content_type',10);$t->unsignedBigInteger('content_id');$t->unsignedBigInteger('legacy_attachment_id');$t->string('legacy_path')->nullable();$t->string('role',20)->default('featured');$t->timestamps();$t->unique(['content_type','content_id','legacy_attachment_id','role']);});
 }
 public function down(): void {Schema::dropIfExists('content_media');Schema::dropIfExists('article_tag');Schema::dropIfExists('article_category');Schema::dropIfExists('editorial_tags');Schema::dropIfExists('editorial_categories');Schema::dropIfExists('pages');Schema::dropIfExists('articles');}
};
