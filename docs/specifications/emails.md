# Transactional Emails

## Architecture
- Business code uses Laravel Mail/Notifications only. Transactional content is resolved by `EmailTemplateRegistry`, then safely rendered by `EmailTemplateRenderer`; business code only supplies an allow-listed data map and sensitive signed links.
- `EmailTemplate` rows are administrative overrides over deterministic application defaults. A new transactional type is declared once in `EmailTemplateRegistry`; it then appears in Filament after opening `Emails > Templates`.
- `MailSettings` stores runtime SMTP settings in `settings.mail_settings`; the SMTP password is Laravel-encrypted and an empty edit never replaces it. The existing Laravel mailer remains the fallback, including the preproduction `log` transport.
- `TransactionalMailService` queues `TemplateMailable` instances and records safe operational metadata in `email_delivery_logs`. The journal never retains a body, token, URL or SMTP secret.
- Transactional messages implement `ShouldQueue`. Preproduction uses the database queue already created by Laravel; failed jobs remain inspectable with `queue:failed` and retries use Laravel worker options.
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
- Contact messages are stored before notification delivery. SMTP failure cannot invalidate an accepted contact submission.

## Operations
- Required worker: `/opt/alt/php84/usr/bin/php artisan queue:work --tries=3 --backoff=30,120,300`.
- Before SMTP is approved, use the configured capture/log transport on preproduction. Real-recipient testing requires an address explicitly supplied by an operator.
