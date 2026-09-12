# Users and Claims

## Scope validated
- Laravel session authentication supports registration, login, logout and password reset.
- Authentication writes are protected by CSRF and rate limited per e-mail/IP pair.
- Legacy users retain `legacy_wp_user_id`. WordPress password hashes are deliberately discarded.
- A legacy WordPress user whose capabilities contain `administrator` is migrated with the V2 `admin` role; an existing V2 role is never downgraded by a repeat migration.
- A migrated account is marked `must_change_password` and can only change its password or log out until it completes that action.
- Password reset also clears the mandatory-change flag after a successful reset.

## Restaurant claims
- A first claim creates no account and starts as `pending_email_verification`; a private identity document plus a single-use, expiring e-mail link are required before it enters the administrator queue.
- A verified restaurateur sees only the restaurant and the certification checkbox; subsequent claims never request identity, company, SIRET or a password and remain manually reviewed.
- Approval associates one verified user with one or more restaurants through `restaurant_claims`. A new first dossier receives an activation link to choose only a password; rejection creates no account.
- `RestaurantPolicy::manage` is enforced server-side; owners cannot access another owner's restaurant.
- A guest who opens a claimable restaurant claim first sees the Contact-form-card styled account explanation, with Login and Registration actions. Both preserve the claim form as Laravel's intended destination; after successful authentication (or the required legacy password change), the user returns to that exact claim form. Claimability is checked before the explanation, when entering the authentication hand-off and again when loading/submitting the protected form.

## Public proposal contributors

- Every public restaurant depositor who confirms their e-mail receives a one-use, seven-day link in that same confirmation message to choose a password. It is protected by a separate 64-character hashed token instead of a secondary signed-URL query string. A new active `restaurant_owner` account is created; an existing ordinary account is attached and upgraded to that role without changing its password.
- The account may manage only restaurants whose `restaurant_submissions.user_id` is that user. This management grant is deliberately distinct from `restaurant_claims`, so the restaurant remains claimable by its real manager.

## Historical listing authorship
- `legacy_restaurant_authorships` preserves only the WordPress `post_author` relationship to its exact migrated restaurant (`legacy_wp_id`).
- It is not a restaurant claim: it never grants a role, ownership, dashboard access, edit permission, or changes `is_claimed`.
- The import accepts an existing source listing only when it has an active V2 user with the exact `legacy_wp_user_id` and an active V2 restaurant with the exact `legacy_wp_id`. The legacy post status is preserved in the audit but does not invalidate an already active exact V2 restaurant. Missing or deleted V2 records are reported and excluded; names and slugs are never fallback matches.

## Deferred
- No mass user migration, final email provider, profile module or final administration design is included in this phase.
