<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('redirect_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path', 2048);
            $table->string('match_type', 16); // exact|regex
            $table->string('query_pattern', 2048)->nullable();
            $table->string('destination', 2048);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('preserve_query')->default(false);
            $table->unsignedInteger('priority')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->string('origin', 32)->default('manual');
            $table->text('source_rule')->nullable();
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
            $table->index(['match_type', 'is_active', 'priority']);
            $table->unique(['source_path', 'match_type', 'query_pattern'], 'redirect_rule_source_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('redirect_rules'); }
};
