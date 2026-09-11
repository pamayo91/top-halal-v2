# Transactional Emails

## Architecture
- Business code uses Laravel Mail/Notifications only. Transactional content is resolved by `EmailTemplateRegistry`, then safely rendered by `EmailTemplateRenderer`; business code only supplies an allow-listed data map and sensitive signed links.
- `EmailTemplate` rows are administrative overrides over deterministic application defaults. A new transactional type is declared once in `EmailTemplateRegistry`; it then appears in Filament after opening `Emails > Templates`. `EmailGlobalSettings` owns the common 600px header/footer layout (display name, managed-media logo, primary colour, footer and optional dynamic year/additional text); template bodies remain plain text only.
- `MailSettings` stores runtime SMTP settings in `settings.mail_settings`; SMTP is the sole back-office transport, the password is Laravel-encrypted and an empty edit never replaces it. Contact recipients, acknowledgement option and post-send message are configured only under `Contact > Réglages`; the public editorial introduction is no longer a setting.
- `TransactionalMailService` queues `TemplateMailable` instances and records safe operational metadata in `email_delivery_logs`. Each tracked job retains its database-queue ID; the journal never retains a body, token, URL or SMTP secret.
- `Emails > Configuration > Envoyer un e-mail de test` is deliberately separate: it calls the configured SMTP transport synchronously and never creates a queue job or a delivery-history row. A successful SMTP hand-off is confirmed in the BO; a sanitised actionable failure is displayed there and recorded in `storage/logs/laravel.log` without credentials.
- Transactional messages implement `ShouldQueue`. Preproduction uses the database queue already created by Laravel; failed jobs remain inspectable with `queue:failed`. Every queued transactional mailable/notification carries four attempts, a 75-second timeout and progressive backoffs of 30, 120 and 300 seconds.
- Controllers only enqueue notifications. A real SMTP failure occurs in the worker and cannot turn a user request into an error page.

## Implemented events
- Email verification, password reset, password-change confirmation, and claim received/accepted/refused now use central templates.
- Claim submitted, accepted and refused.
- `mail:test address@example.com` queues a neutral test message without printing configuration.
- The legacy-account notification template exists for the future campaign and is never dispatched by the migration.

## Security
- Verification links use Laravel temporary signed URLs; reset links use Laravel password broker tokens and expire after 60 minutes.
- Credentials remain server-only. No campaign is sent to legacy users in this phase.
- Template variables are plain allow-listed placeholders only (`{{ variable }}`); Blade, PHP and unknown placeholders are never evaluated.
- Template bodies are normalised to LF at render time and the one-off forward migration rewrites historical literal `\\n` sequences. HTML and text alternatives both use the same rendered body; template text and substitutions are Blade-escaped and never treated as administrator-supplied HTML.
- Contact messages are stored before notification delivery. SMTP failure cannot invalidate an accepted contact submission.

## Operations

`Emails > Templates` présente l’identifiant technique, le nom fonctionnel et la description de chaque template. Les deux derniers proviennent du registre applicatif et ne sont pas des champs administrables.

- On o2switch mutualised preproduction, Cron runs `scripts/run-queue-worker-cron.sh` every minute. It uses non-blocking `flock`, database queue `retry_after=90`, `queue:work --timeout=75 --tries=4 --stop-when-empty`, and exits after the queue drains. The job-level backoff array is serialised by Laravel 13 into its queue payload; it is not passed as an unverified CLI list.
- Before SMTP is approved, use the configured capture/log transport on preproduction. Real-recipient testing requires an address explicitly supplied by an operator.

## Historique opérationnel

`Emails > Historique` is an operational audit trail, not a delivery queue clone. It presents French status and template labels, recipient/date/type filters, details (including safe SMTP error, attempt count and Message-ID when supplied by SMTP), and lightweight status counters.

- `En attente` is shown only while the associated Laravel `jobs` row exists. The Cron reconciliation command expires missing queued jobs without deleting their audit record.
- `En cours` is a worker-held job and cannot be cancelled. `Envoyé`, `Échec`, `Annulé` and `Expiré` are terminal states.
- A pending job can be made available immediately or cancelled; a failed job is retried from Laravel's existing `failed_jobs` payload. Neither operation creates a second delivery-history row or offers a resend for an already sent message.
- The Cron script calls `emails:reconcile-delivery-logs` before its flock-protected finite worker.
- A separate daily Cron runs `emails:purge-delivery-logs --days=60`. It deletes only terminal `Annulé` and `Expiré` records older than 60 days; `Envoyé` and `Échec` remain retained. Operators can audit the next run safely with `emails:purge-delivery-logs --days=60 --dry-run`.
