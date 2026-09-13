<?php

namespace App\Services;

use App\Models\{EditorialContentReport, Setting};

class ContentReportMailer
{
    public function notifyTeam(EditorialContentReport $report): void
    {
        $recipient = Setting::query()->where('key', 'contact_settings')->first()?->value['recipient'] ?? null;
        if (blank($recipient)) return;

        app(TransactionalMailService::class)->queue('content_report_admin_review', $recipient, [
            'site_name' => config('app.name', 'Top Halal'),
            'report_type' => ['restaurant' => 'Restaurant', 'article' => 'Article', 'page' => 'Page'][$report->content_type] ?? $report->content_type,
            'content_title' => $report->content_title,
            'report_message' => $report->message,
            'reporter_name' => $report->reporter_name ?: 'Visiteur',
            'reporter_email' => $report->reporter_email,
            'identity_status' => $report->is_authenticated ? 'Compte connecté' : 'E-mail vérifié',
            'admin_url' => url('/admin/content-reports/'.$report->id.'/edit'),
        ]);
    }
}
