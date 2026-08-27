<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('admin_audit_logs', function (Blueprint $t): void {
            $t->id(); $t->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 80); $t->string('subject_type', 160); $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable(); $t->char('ip_hash', 64)->nullable(); $t->timestamps();
            $t->index(['subject_type', 'subject_id']); $t->index(['action', 'created_at']);
        });
        Schema::create('settings', function (Blueprint $t): void {
            $t->id(); $t->string('key')->unique(); $t->json('value')->nullable(); $t->string('group', 40)->default('general'); $t->timestamps();
        });
        Schema::table('restaurants', function (Blueprint $t): void {
            $t->text('seo_title')->nullable()->after('contact_email'); $t->text('seo_description')->nullable()->after('seo_title');
        });
        foreach (['articles', 'pages'] as $table) Schema::table($table, function (Blueprint $t): void {
            $t->text('excerpt')->nullable()->after('content_html'); $t->string('source_type', 20)->default('manual')->after('status');
            $t->boolean('ai_disclosure_visible')->default(false)->after('source_type'); $t->json('ai_provenance')->nullable()->after('ai_disclosure_visible');
        });
    }
    public function down(): void { foreach (['articles', 'pages'] as $table) Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['excerpt','source_type','ai_disclosure_visible','ai_provenance'])); Schema::table('restaurants', fn (Blueprint $t) => $t->dropColumn(['seo_title','seo_description'])); Schema::dropIfExists('settings'); Schema::dropIfExists('admin_audit_logs'); }
};
