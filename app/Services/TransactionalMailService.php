<?php

namespace App\Services;

use App\Exceptions\EmailDeliveryUnavailableException;
use App\Mail\TemplateMailable;
use App\Models\EmailDeliveryLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class TransactionalMailService
{
    public function queue(string $templateKey, string $recipient, array $values = [], ?string $replyTo = null): ?EmailDeliveryLog
    {
        $rendered = app(EmailTemplateRenderer::class)->render($templateKey, $values);

        return DB::transaction(function () use ($templateKey, $recipient, $values, $replyTo, $rendered): EmailDeliveryLog {
            $log = EmailDeliveryLog::create([
                'template_key' => $templateKey,
                'recipient' => $recipient,
                'subject' => $rendered['subject'] ?? null,
                'status' => EmailDeliveryLog::STATUS_QUEUED,
            ]);

            $jobId = Mail::to($recipient)->queue(new TemplateMailable($templateKey, $values, $log->id, $replyTo));
            $log->update(['queue_job_id' => is_numeric($jobId) ? (int) $jobId : null]);

            return $log->fresh();
        });
    }

    public function sendNow(EmailDeliveryLog $log): void
    {
        DB::transaction(function () use ($log): void {
            $log = EmailDeliveryLog::lockForUpdate()->findOrFail($log->id);
            $this->requireQueued($log);
            $job = $this->pendingJob($log, true);

            if (! $job) {
                $this->expire($log);
                throw new EmailDeliveryUnavailableException('Le job Laravel associé n’existe plus ; l’e-mail a été marqué comme expiré.');
            }

            if ($job->reserved_at) {
                throw new EmailDeliveryUnavailableException('L’e-mail est déjà en cours de traitement.');
            }

            DB::table('jobs')->where('id', $job->id)->update(['available_at' => now()->timestamp]);
        });
    }

    public function cancel(EmailDeliveryLog $log): void
    {
        DB::transaction(function () use ($log): void {
            $log = EmailDeliveryLog::lockForUpdate()->findOrFail($log->id);
            $this->requireQueued($log);
            $job = $this->pendingJob($log, true);

            if (! $job) {
                $this->expire($log);
                return;
            }

            if ($job->reserved_at) {
                throw new EmailDeliveryUnavailableException('L’e-mail est déjà en cours de traitement et ne peut plus être annulé.');
            }

            DB::table('jobs')->where('id', $job->id)->whereNull('reserved_at')->delete();
            $log->update([
                'status' => EmailDeliveryLog::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'error_message' => null,
            ]);
        });
    }

    public function retry(EmailDeliveryLog $log): void
    {
        DB::transaction(function () use ($log): void {
            $log = EmailDeliveryLog::lockForUpdate()->findOrFail($log->id);

            if ($log->status !== EmailDeliveryLog::STATUS_FAILED || blank($log->failed_job_uuid)) {
                throw new EmailDeliveryUnavailableException('Aucun job en échec réessayable n’est associé à cet e-mail.');
            }

            $failed = app('queue.failer')->find($log->failed_job_uuid);

            if (! $failed) {
                throw new EmailDeliveryUnavailableException('Le job en échec associé a expiré de Laravel ; il ne peut plus être rejoué sans créer un nouvel e-mail.');
            }

            $jobId = Queue::connection('database')->pushRaw($failed->payload, $failed->queue);
            app('queue.failer')->forget($log->failed_job_uuid);

            $log->update([
                'status' => EmailDeliveryLog::STATUS_QUEUED,
                'queue_job_id' => is_numeric($jobId) ? (int) $jobId : null,
                'failed_job_uuid' => null,
                'error_message' => null,
            ]);
        });
    }

    public function expireMissingQueuedJobs(): int
    {
        $expired = 0;

        EmailDeliveryLog::query()->whereIn('status', [EmailDeliveryLog::STATUS_QUEUED, EmailDeliveryLog::STATUS_PROCESSING])->orderBy('id')->each(function (EmailDeliveryLog $log) use (&$expired): void {
            DB::transaction(function () use ($log, &$expired): void {
                $locked = EmailDeliveryLog::lockForUpdate()->find($log->id);

                if ($locked && ! $this->pendingJob($locked) && ($locked->status === EmailDeliveryLog::STATUS_QUEUED || $locked->last_attempt_at?->lte(now()->subSeconds((int) config('queue.connections.database.retry_after', 90))))) {
                    $this->expire($locked);
                    $expired++;
                }
            });
        });

        return $expired;
    }

    public function purgeTerminalLogs(int $days = 60, bool $dryRun = false): int
    {
        $cutoff = now()->subDays($days);
        $query = EmailDeliveryLog::query()->where(function ($query) use ($cutoff): void {
            $query->where(function ($query) use ($cutoff): void {
                $query->where('status', EmailDeliveryLog::STATUS_CANCELLED)
                    ->where(function ($query) use ($cutoff): void {
                        $query->where('cancelled_at', '<=', $cutoff)
                            ->orWhere(function ($query) use ($cutoff): void {
                                $query->whereNull('cancelled_at')->where('created_at', '<=', $cutoff);
                            });
                    });
            })->orWhere(function ($query) use ($cutoff): void {
                $query->where('status', EmailDeliveryLog::STATUS_EXPIRED)
                    ->where(function ($query) use ($cutoff): void {
                        $query->where('expired_at', '<=', $cutoff)
                            ->orWhere(function ($query) use ($cutoff): void {
                                $query->whereNull('expired_at')->where('created_at', '<=', $cutoff);
                            });
                    });
            });
        });

        return $dryRun ? $query->count() : $query->delete();
    }

    private function pendingJob(EmailDeliveryLog $log, bool $lock = false): ?object
    {
        if (! $log->queue_job_id) {
            return null;
        }

        $query = DB::table('jobs')->where('id', $log->queue_job_id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function requireQueued(EmailDeliveryLog $log): void
    {
        if ($log->status !== EmailDeliveryLog::STATUS_QUEUED) {
            throw new EmailDeliveryUnavailableException('Cette action n’est disponible que pour un e-mail réellement en attente.');
        }
    }

    private function expire(EmailDeliveryLog $log): void
    {
        $log->update([
            'status' => EmailDeliveryLog::STATUS_EXPIRED,
            'expired_at' => now(),
            'error_message' => 'Le job Laravel associé n’existe plus.',
        ]);
    }
}
