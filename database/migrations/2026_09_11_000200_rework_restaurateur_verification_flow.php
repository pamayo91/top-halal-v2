<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('restaurant_claims', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email')->nullable()->after('full_name');
            $table->timestamp('email_verified_at')->nullable()->after('identity_document_path');
            $table->string('email_verification_token', 64)->nullable()->unique()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_token');
            $table->string('activation_token', 64)->nullable()->unique()->after('email_verification_expires_at');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_token');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_claims', function (Blueprint $table): void {
            $table->dropUnique(['email_verification_token']);
            $table->dropUnique(['activation_token']);
            $table->dropColumn(['email', 'email_verified_at', 'email_verification_token', 'email_verification_expires_at', 'activation_token', 'activation_expires_at']);
        });
    }
};
