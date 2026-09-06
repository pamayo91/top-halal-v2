# Users and Claims

## Scope validated
- Laravel session authentication supports registration, login, logout and password reset.
- Authentication writes are protected by CSRF and rate limited per e-mail/IP pair.
- Legacy users retain `legacy_wp_user_id`. WordPress password hashes are deliberately discarded.
- A legacy WordPress user whose capabilities contain `administrator` is migrated with the V2 `admin` role; an existing V2 role is never downgraded by a repeat migration.
- A migrated account is marked `must_change_password` and can only change its password or log out until it completes that action.
- Password reset also clears the mandatory-change flag after a successful reset.

## Restaurant claims
- A claim starts as `pending`; an administrator can approve or reject it with audit timestamps and reviewer reference.
- Approval associates the user and restaurant through `restaurant_claims` and promotes only a standard user to `restaurant_owner`.
- `RestaurantPolicy::manage` is enforced server-side; owners cannot access another owner's restaurant.

## Historical listing authorship
- `legacy_restaurant_authorships` preserves only the WordPress `post_author` relationship to its exact migrated restaurant (`legacy_wp_id`).
- It is not a restaurant claim: it never grants a role, ownership, dashboard access, edit permission, or changes `is_claimed`.
- The import accepts an existing source listing only when it has an active V2 user with the exact `legacy_wp_user_id` and an active V2 restaurant with the exact `legacy_wp_id`. The legacy post status is preserved in the audit but does not invalidate an already active exact V2 restaurant. Missing or deleted V2 records are reported and excluded; names and slugs are never fallback matches.

## Deferred
- No mass user migration, final email provider, profile module or final administration design is included in this phase.
