# Email

## Scope
Transactional emails for account verification/reset, comment/review moderation, restaurant claims, admin notifications, contact forms and future business workflows.

## Architecture
Use Laravel Mail/Notifications behind configuration so SMTP or an API mail provider can be changed without rewriting domain logic.
Queue non-critical mail. Log delivery failures and retry safely.

## Administration
- sender name/address;
- reply-to where appropriate;
- provider/SMTP config via environment/secrets;
- test-email action;
- template preview where practical;
- operational log/status without exposing secrets.

## Deliverability
Deployment checklist must cover SPF, DKIM and DMARC for the sending domain/provider.
