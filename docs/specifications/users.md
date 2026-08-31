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

## Deferred
- No mass user migration, final email provider, profile module or final administration design is included in this phase.
