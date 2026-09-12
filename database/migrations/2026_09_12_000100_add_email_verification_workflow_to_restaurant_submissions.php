<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->string('status', 32)->default('pending_email_verification')->after('submitter_role');
            $table->timestamp('email_verified_at')->nullable()->after('status');
            $table->string('email_verification_token', 64)->nullable()->after('email_verified_at');
            $table->timestamp('email_verification_expires_at')->nullable()->after('email_verification_token');
            $table->index('status');
        });

        // Existing proposals predate e-mail verification. Keep those already in the
        // moderation queue manageable without pretending that they were verified.
        DB::table('restaurant_submissions')
            ->whereIn('restaurant_id', DB::table('restaurants')->where('status', 'published')->select('id'))
            ->update(['status' => 'published']);
        DB::table('restaurant_submissions')
            ->whereNotIn('restaurant_id', DB::table('restaurants')->where('status', 'published')->select('id'))
            ->update(['status' => 'pending_admin_review']);
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'email_verified_at', 'email_verification_token', 'email_verification_expires_at']);
        });
    }
};
