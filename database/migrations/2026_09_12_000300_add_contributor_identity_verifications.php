<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'login_enabled')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('login_enabled')->default(true)->after('password');
            });
        }

        if (! Schema::hasColumn('restaurant_reviews', 'user_id')) {
            Schema::table('restaurant_reviews', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->after('restaurant_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('comments', 'user_id')) {
            Schema::table('comments', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->after('page_id')->constrained()->nullOnDelete();
            });
        }

        Schema::create('contribution_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('author_name', 100);
            $table->string('contribution_type', 20)->index();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->json('payload');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->string('created_contribution_type', 20)->nullable();
            $table->unsignedBigInteger('created_contribution_id')->nullable();
            $table->timestamps();
            $table->index(['contribution_type', 'target_type', 'target_id'], 'contribution_verification_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_verifications');
        Schema::table('comments', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('restaurant_reviews', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('login_enabled'));
    }
};
