<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_delivery_logs', function (Blueprint $table): void {
            $table->string('subject', 255)->nullable()->after('recipient');
            $table->unsignedBigInteger('queue_job_id')->nullable()->index()->after('status');
            $table->uuid('failed_job_uuid')->nullable()->index()->after('queue_job_id');
            $table->string('message_id', 255)->nullable()->after('failed_job_uuid');
            $table->timestamp('sent_at')->nullable()->after('last_attempt_at');
            $table->timestamp('cancelled_at')->nullable()->after('sent_at');
            $table->timestamp('expired_at')->nullable()->after('cancelled_at');
        });

        DB::table('email_delivery_logs')
            ->where('status', 'queued')
            ->update([
                'status' => 'expired',
                'expired_at' => now(),
                'error_message' => 'Le job Laravel associé n’existe plus.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('email_delivery_logs', function (Blueprint $table): void {
            $table->dropIndex(['queue_job_id']);
            $table->dropIndex(['failed_job_uuid']);
            $table->dropColumn(['subject', 'queue_job_id', 'failed_job_uuid', 'message_id', 'sent_at', 'cancelled_at', 'expired_at']);
        });
    }
};
