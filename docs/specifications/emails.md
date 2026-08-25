# Transactional Emails

## Architecture
- Business code uses Laravel Mail/Notifications only. SMTP is configured exclusively through environment variables; API transports can be added to `config/mail.php` later without changing controllers.
- Transactional messages implement `ShouldQueue`. Preproduction uses the database queue already created by Laravel; failed jobs remain inspectable with `queue:failed` and retries use Laravel worker options.
- Controllers only enqueue notifications. A real SMTP failure occurs in the worker and cannot turn a user request into an error page.

## Implemented events
- Email verification, password reset, password-change confirmation.
- Claim submitted, accepted and refused.
- `mail:test address@example.com` queues a neutral test message without printing configuration.
- The legacy-account notification template exists for the future campaign and is never dispatched by the migration.

## Security
- Verification links use Laravel temporary signed URLs; reset links use Laravel password broker tokens and expire after 60 minutes.
- Credentials remain server-only. No campaign is sent to legacy users in this phase.

## Operations
- Required worker: `/opt/alt/php84/usr/bin/php artisan queue:work --tries=3 --backoff=30,120,300`.
- Before SMTP is approved, use the configured capture/log transport on preproduction. Real-recipient testing requires an address explicitly supplied by an operator.
