<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;

class AdminAudit
{
    public function record(string $action, Model|string $subject, array $changes = []): void
    {
        $user = request()->user() ?? auth()->user();
        if (!$user) return;
        AdminAuditLog::create([
            'admin_id' => $user->id, 'action' => $action,
            'subject_type' => $subject instanceof Model ? $subject::class : $subject,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'changes' => collect($changes)->except(['password', 'remember_token', 'token', 'destination_url'])->all(),
            'ip_hash' => hash('sha256', (string) request()->ip()),
        ]);
    }
}
