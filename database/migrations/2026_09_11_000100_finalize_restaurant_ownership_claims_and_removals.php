<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('restaurant_claims', function (Blueprint $table): void {
            $table->string('full_name')->nullable()->after('user_id');
            $table->string('company')->nullable()->after('full_name');
            $table->string('siret', 14)->nullable()->after('company');
            $table->boolean('certified')->default(false)->after('siret');
            $table->string('identity_document_path')->nullable()->after('certified');
            $table->string('source', 30)->default('claim')->after('identity_document_path');
            $table->index(['restaurant_id', 'status']);
        });
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('restaurant_id')->constrained()->nullOnDelete();
            $table->string('owner_full_name')->nullable()->after('submitter_role');
            $table->string('owner_company')->nullable()->after('owner_full_name');
            $table->string('owner_siret', 14)->nullable()->after('owner_company');
            $table->boolean('owner_certified')->default(false)->after('owner_siret');
        });
        Schema::create('restaurant_removal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['restaurant_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('restaurant_removal_requests'); Schema::table('restaurant_submissions', function (Blueprint $t): void {$t->dropConstrainedForeignId('user_id'); $t->dropColumn(['owner_full_name','owner_company','owner_siret','owner_certified']);}); Schema::table('restaurant_claims', function (Blueprint $t): void {$t->dropIndex(['restaurant_id','status']);$t->dropColumn(['full_name','company','siret','certified','identity_document_path','source']);}); }
};
