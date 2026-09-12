<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->string('activation_token')->nullable()->after('email_verification_expires_at');
            $table->timestamp('activation_expires_at')->nullable()->after('activation_token');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->dropColumn(['activation_token', 'activation_expires_at']);
        });
    }
};
