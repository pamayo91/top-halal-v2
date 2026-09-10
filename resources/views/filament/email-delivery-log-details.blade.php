<dl class="grid gap-4 text-sm sm:grid-cols-2">
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Type</dt><dd>{{ $record->templateLabel() }}</dd></div>
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Destinataire</dt><dd>{{ $record->recipient }}</dd></div>
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Objet</dt><dd>{{ $record->subject ?: 'Non disponible' }}</dd></div>
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Statut</dt><dd>{{ $record->statusLabel() }}</dd></div>
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Créé le</dt><dd>{{ $record->created_at?->format('d/m/Y H:i') }}</dd></div>
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Envoyé le / dernier essai</dt><dd>{{ ($record->sent_at ?? $record->last_attempt_at)?->format('d/m/Y H:i') ?: 'Aucun essai' }}</dd></div>
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Tentatives</dt><dd>{{ $record->attempts }}</dd></div>
    <div><dt class="font-medium text-gray-700 dark:text-gray-300">Message-ID</dt><dd class="break-all">{{ $record->message_id ?: 'Non disponible' }}</dd></div>
    @if ($record->safeErrorMessage())
        <div class="sm:col-span-2"><dt class="font-medium text-gray-700 dark:text-gray-300">Erreur</dt><dd class="mt-1 whitespace-pre-wrap break-words rounded bg-danger-50 p-3 text-danger-700 dark:bg-danger-950 dark:text-danger-200">{{ $record->safeErrorMessage() }}</dd></div>
    @endif
</dl>
