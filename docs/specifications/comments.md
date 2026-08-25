# Comments

## Legacy migration
- Preserve legitimate approved and pending comments.
- Preserve dates and parent/reply hierarchy.
- Do not migrate spam comments.
- Keep a `legacy_wp_comment_id` for reconciliation.
- Accept only WordPress normal `comment`/empty types with status `1` or `0`; explicitly exclude spam, trash, pingbacks, trackbacks and ListingPro `review` comments.
- The read-only source is queried exclusively through `legacy_wp`. The migrator supports an explicit `--apply`; its default and `--dry-run` never write V2.
- Legacy user HTML is converted to text and escaped on rendering. Existing URL-like text is retained as non-clickable text for historical fidelity; only new submissions reject URLs.
- The temporary moderation backend is `comments:moderate {id} --status=approved|rejected|spam` or `--delete`, pending the dedicated admin module.

## New comments
Fields: author name, email, content; optional authenticated user relationship.
- Moderated workflow: pending -> approved/rejected/spam.
- URLs are forbidden. Reject `http://`, `https://`, `www.`, link markup and robust URL/domain-like patterns server-side.
- Comments render as escaped/sanitized safe text; user HTML is not executed.
- Email is private.
- Add honeypot + rate limiting + CSRF + spam controls.
- Support threaded replies via `parent_id`.
- Admin can approve, reject, mark spam and delete through the temporary technical command; a full authenticated interface belongs to the future accounts/admin scope.

## Tests
- Valid comment -> pending -> approve -> visible.
- Comment with URL -> rejected with understandable error.
- HTML/XSS payload -> never executable.
- Rate limit/honeypot behavior.
- Thread hierarchy preserved after migration.
