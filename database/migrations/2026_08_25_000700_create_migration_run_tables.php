<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('migration_runs', function (Blueprint $table): void {
            $table->id(); $table->uuid('run_uuid')->unique(); $table->string('status', 20)->index();
            $table->unsignedInteger('batch_size'); $table->json('only')->nullable(); $table->timestamp('started_at'); $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
        Schema::create('migration_checkpoints', function (Blueprint $table): void {
            $table->id(); $table->foreignId('migration_run_id')->constrained()->cascadeOnDelete(); $table->string('phase', 40); $table->unsignedBigInteger('last_legacy_id')->default(0); $table->string('status', 20)->default('pending'); $table->json('counters')->nullable(); $table->timestamps(); $table->unique(['migration_run_id', 'phase']);
        });
        Schema::create('migration_anomalies', function (Blueprint $table): void {
            $table->id(); $table->foreignId('migration_run_id')->constrained()->cascadeOnDelete(); $table->string('phase', 40); $table->unsignedBigInteger('legacy_id')->nullable(); $table->string('code', 100); $table->string('severity', 20)->default('warning'); $table->json('context')->nullable(); $table->timestamps(); $table->index(['migration_run_id', 'phase']);
        });
    }
    public function down(): void { Schema::dropIfExists('migration_anomalies'); Schema::dropIfExists('migration_checkpoints'); Schema::dropIfExists('migration_runs'); }
};
