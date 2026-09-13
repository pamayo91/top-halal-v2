<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->text('admin_rejection_reason')->nullable()->after('duplicate_details');
            $table->string('rejection_reference', 40)->nullable()->unique()->after('admin_rejection_reason');
            $table->timestamp('rejected_at')->nullable()->after('rejection_reference');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropUnique(['rejection_reference']);
            $table->dropColumn(['admin_rejection_reason', 'rejection_reference', 'rejected_at']);
        });
    }
};
