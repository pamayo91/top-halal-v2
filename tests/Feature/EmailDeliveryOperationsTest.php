<?php

namespace Tests\Feature;

use App\Exceptions\EmailDeliveryUnavailableException;
use App\Mail\TemplateMailable;
use App\Models\EmailDeliveryLog;
use App\Services\TransactionalMailService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class EmailDeliveryOperationsTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
    }

    public function test_queued_email_is_linked_to_its_real_database_job(): void
    {
        $log = app(TransactionalMailService::class)->queue('contact_confirmation', 'user@example.test');

        $this->assertNotNull($log);
        $this->assertSame(EmailDeliveryLog::STATUS_QUEUED, $log->status);
        $this->assertNotNull($log->queue_job_id);
        $this->assertDatabaseHas('jobs', ['id' => $log->queue_job_id]);
    }

    public function test_send_now_releases_the_existing_job_without_creating_a_second_one(): void
    {
        $log = app(TransactionalMailService::class)->queue('contact_confirmation', 'user@example.test');
        DB::table('jobs')->where('id', $log->queue_job_id)->update(['available_at' => now()->addHour()->timestamp]);

        app(TransactionalMailService::class)->sendNow($log);

        $this->assertDatabaseCount('jobs', 1);
        $this->assertLessThanOrEqual(now()->timestamp, DB::table('jobs')->where('id', $log->queue_job_id)->value('available_at'));
    }

    public function test_cancellation_removes_only_the_linked_pending_job_and_cannot_be_repeated(): void
    {
        $log = app(TransactionalMailService::class)->queue('contact_confirmation', 'user@example.test');
        app(TransactionalMailService::class)->cancel($log);

        $this->assertDatabaseMissing('jobs', ['id' => $log->queue_job_id]);
        $this->assertDatabaseHas('email_delivery_logs', ['id' => $log->id, 'status' => EmailDeliveryLog::STATUS_CANCELLED]);
        $this->expectException(EmailDeliveryUnavailableException::class);
        app(TransactionalMailService::class)->cancel($log->fresh());
    }

    public function test_missing_pending_job_is_retained_as_expired_not_left_queued(): void
    {
        $log = app(TransactionalMailService::class)->queue('contact_confirmation', 'user@example.test');
        DB::table('jobs')->where('id', $log->queue_job_id)->delete();

        $this->assertSame(1, app(TransactionalMailService::class)->expireMissingQueuedJobs());
        $this->assertDatabaseHas('email_delivery_logs', ['id' => $log->id, 'status' => EmailDeliveryLog::STATUS_EXPIRED]);
    }

    public function test_retry_reuses_the_failed_payload_once_without_duplicate_delivery_jobs(): void
    {
        $service = app(TransactionalMailService::class);
        $log = $service->queue('contact_confirmation', 'user@example.test');
        $payload = DB::table('jobs')->where('id', $log->queue_job_id)->value('payload');
        DB::table('jobs')->where('id', $log->queue_job_id)->delete();
        $uuid = app('queue.failer')->log('database', 'default', $payload, new RuntimeException('SMTP password=not-for-display'));
        $log->update(['status' => EmailDeliveryLog::STATUS_FAILED, 'failed_job_uuid' => $uuid]);

        $service->retry($log->fresh());

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('email_delivery_logs', ['id' => $log->id, 'status' => EmailDeliveryLog::STATUS_QUEUED, 'failed_job_uuid' => null]);
        $this->expectException(EmailDeliveryUnavailableException::class);
        $service->retry($log->fresh());
    }

    public function test_a_job_can_claim_a_log_only_once_while_its_attempt_is_active(): void
    {
        $log = app(TransactionalMailService::class)->queue('contact_confirmation', 'user@example.test');
        $mail = new TemplateMailable('contact_confirmation', [], $log->id);
        $mail->build();

        $this->assertDatabaseHas('email_delivery_logs', ['id' => $log->id, 'status' => EmailDeliveryLog::STATUS_PROCESSING, 'attempts' => 1]);
        $this->expectException(EmailDeliveryUnavailableException::class);
        (new TemplateMailable('contact_confirmation', [], $log->id))->build();
    }

    public function test_delivery_errors_are_sanitised_before_being_kept_in_the_history(): void
    {
        $log = EmailDeliveryLog::create(['template_key' => 'contact_confirmation', 'recipient' => 'user@example.test', 'status' => EmailDeliveryLog::STATUS_PROCESSING]);
        (new TemplateMailable('contact_confirmation', [], $log->id))->failed(new RuntimeException('SMTP rejected password=not-for-display'));

        $this->assertDatabaseHas('email_delivery_logs', ['id' => $log->id, 'status' => EmailDeliveryLog::STATUS_FAILED]);
        $this->assertStringNotContainsString('not-for-display', $log->fresh()->error_message);
    }
}
