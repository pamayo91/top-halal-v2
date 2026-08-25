# Comments

## Legacy migration
- Preserve legitimate approved and pending comments.
- Preserve dates and parent/reply hierarchy.
- Do not migrate spam comments.
- Keep a `legacy_wp_comment_id` for reconciliation.

## New comments
Fields: author name, email, content; optional authenticated user relationship.
- Moderated workflow: pending -> approved/rejected/spam.
- URLs are forbidden. Reject `http://`, `https://`, `www.`, link markup and robust URL/domain-like patterns server-side.
- Comments render as escaped/sanitized safe text; user HTML is not executed.
- Email is private.
- Add honeypot + rate limiting + CSRF + spam controls.
- Support threaded replies via `parent_id`.
- Admin can approve, reject, mark spam, delete, edit when justified, and reply.

## Tests
- Valid comment -> pending -> approve -> visible.
- Comment with URL -> rejected with understandable error.
- HTML/XSS payload -> never executable.
- Rate limit/honeypot behavior.
- Thread hierarchy preserved after migration.
