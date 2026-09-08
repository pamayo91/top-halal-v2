<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('email_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key', 100); $table->string('recipient', 254);
            $table->string('status', 20)->default('queued'); $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable(); $table->text('error_message')->nullable();
            $table->timestamps(); $table->index(['status', 'created_at']);
        });
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id(); $table->string('name', 120); $table->string('email', 254); $table->string('subject', 180);
            $table->text('message'); $table->string('status', 20)->default('new'); $table->char('ip_hash', 64)->nullable();
            $table->timestamps(); $table->index(['status', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('contact_messages'); Schema::dropIfExists('email_delivery_logs'); Schema::dropIfExists('email_templates'); }
};
