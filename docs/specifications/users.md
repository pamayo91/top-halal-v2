# Users and Claims

## Scope validated
- Laravel session authentication supports login, logout and password reset. There is no generic public self-registration page.
- Authentication writes are protected by CSRF and rate limited per e-mail/IP pair.
- Legacy users retain `legacy_wp_user_id`. WordPress password hashes are deliberately discarded.
- A legacy WordPress user whose capabilities contain `administrator` is migrated with the V2 `admin` role; an existing V2 role is never downgraded by a repeat migration.
- A migrated account is marked `must_change_password` and can only change its password or log out until it completes that action.
- Password reset also clears the mandatory-change flag after a successful reset.
- The login, forgotten-password, reset-password, e-mail-verification and password-change pages use the same responsive public form-card language as restaurant claims and public restaurant proposals: calm tinted background, centred card, editorial hierarchy, icon-assisted fields and full-width action. The authenticated restaurant-edit and removal-request forms use the same language while retaining their dedicated address and moderation controls.

## Restaurant claims
- A first claim creates no account and starts as `pending_email_verification`; a private identity document plus a single-use, expiring e-mail link are required before it enters the administrator queue.
- A verified restaurateur sees only the restaurant and the certification checkbox; subsequent claims never request identity, company, SIRET or a password and remain manually reviewed.
- Approval associates one verified user with one or more restaurants through `restaurant_claims`. A new first dossier receives an activation link to choose only a password; rejection creates no account.
- `RestaurantPolicy::manage` is enforced server-side; owners cannot access another owner's restaurant.
- A guest who opens a claimable restaurant claim sees the direct first-claim form, while an existing verified restaurateur can log in in the same page and resume that exact form. The former `/restaurants/{restaurant}/claim/register` hand-off remains a compatibility redirect to the claim form; it never opens generic self-registration. Claimability is checked when entering and submitting the claim.
- The restaurant-owner account dashboard exposes a CSRF-protected logout action that invalidates the current session through the shared authentication endpoint.

## Public proposal contributors

- Every public restaurant depositor who confirms their e-mail is attached to the unique case-insensitive e-mail `User`. A new active baseline `user` account receives a one-use, seven-day link to choose a password. An existing account receives that link only when it is active but not yet normally connectable: `login_enabled=false` or `must_change_password=true`. An already active account (`login_enabled=true`, `status=active`, `must_change_password=false`) is attached without changing any account attribute and receives no new activation token or link. An administratively `disabled` account is never reactivated by a public proposal. Activation uses a separate 64-character hashed token instead of a secondary signed-URL query string. Choosing the password authenticates the depositor immediately and opens `Mon compte`; the temporary management grant is carried only by the linked submission.
- A later visit to a consumed activation URL never allows a password change. It displays a noindex explanation that the space is already active and offers the login route instead of an opaque 404.
- Expired public-proposal and first-claim verification links (24 hours), and eligible activation links (7 days), show the same noindex explanation and a CSRF-protected “Renvoyer un nouveau lien” action. The action is limited to three requests per object/IP/hour, keeps the original e-mail and replaces the stored SHA-256 token hash atomically; the old link immediately becomes unusable. Rejected/published proposals, rejected claims, consumed links and administratively disabled accounts never offer a resend.
- A depositor may manage a restaurant linked by `restaurant_submissions.user_id` only while it has no approved claim. This temporary management grant is distinct from ownership, so the restaurant remains claimable by its real manager. A pending or rejected claim changes nothing; an approved claim transfers current management to its owner, removes the listing from the former non-owner depositor's account and preserves the submission for audit. A former depositor reaching an edit or removal URL is redirected to an explanatory page, never a raw 403; it includes a Contact CTA to contest the transfer.
- When a confirmed public depositor explicitly declared themselves the manager/owner, confirmation prepares exactly one `new_submission` claim in `pending_publication`. It grants no ownership before Top Halal publishes the restaurant. Publication atomically advances that existing claim through the normal approval mechanism, making it the unique approved ownership relation; a non-manager depositor never receives this claim.
- The back-office computes a business profile rather than exposing a restaurant authority role: `Administrateur` comes from the technical role; `Restaurateur` comes from an approved claim or an exact V2 legacy-authorship relation; `Déposant` comes from a linked public submission and is labelled separately when the manager/owner was declared; all others, including non-connectable contribution identities, are `Utilisateur`. The primary profile never grants a permission itself. The list separately exposes connection availability, current management, and the breakdown of ownership, submissions and legacy relations.

## Contributor identities (reviews and comments)

- A review or editorial comment is always associated with one `User` once its author has proved control of the submitted e-mail address. A unique e-mail therefore maps to one identity only.
- A contributor identity created by this flow has `login_enabled=false`, an undisclosed random password, role `user` and no claim or restaurant-management grant. It cannot authenticate or receive a usable password-reset link merely because it contributed.
- When that same verified e-mail later submits a restaurant, the exact same identity is attached to the submission and receives its activation link, but remains `login_enabled=false` until that link is validly used to choose a password. Only this final activation enables login, password reset and the contributor space; its existing reviews and comments remain linked to the same `user_id` throughout.
- The one-time V2 correction also disables the 77 historical contributor-only identities that received `login_enabled=true` as the column's migration default. Its idempotent scope is limited to unactivated migrated `user` accounts with at least one exact V2 review/comment match and no claim, submission or historical restaurant authorship; it changes neither roles nor contributions or restaurant relations.
- An authenticated account proves its own identity directly. An anonymous browser must either hold a server-side session proof for the exact `User` or complete a fresh verification. The proof stores only a user ID and expiry in the Laravel session, never a freely supplied e-mail, and expires after 30 days (or earlier when its session ends).
- An e-mail's historical `email_verified_at` alone is never enough to submit a contribution: absent a valid current proof, a new one-use verification is required.
- `contributions:purge-verifications` removes only verification rows consumed more than seven days ago or unconsumed rows expired more than seven days ago. It is scheduled daily, supports `--dry-run`, processes bounded batches and never removes the related user, contribution or e-mail history.
- Expired review, comment and correction-report verifications use the same noindex resend page and replacement-token rule. A consumed token gives an idempotent confirmation; a deleted/unpublished target or disabled identity gives a final state without resend.

## Historical listing authorship
- `legacy_restaurant_authorships` preserves only the WordPress `post_author` relationship to its exact migrated restaurant (`legacy_wp_id`).
- It is not a restaurant claim and never changes `is_claimed` or creates ownership data. It is nevertheless a first-class source of effective management for that exact linked restaurant: the historical manager sees it in `Mon compte`, may edit it through `RestaurantPolicy`, and blocks a competing first claim.
- It makes the historical account visibly `Restaurateur` in the back-office profile, with an explicit historical management indicator.
- The import accepts an existing source listing only when it has an active V2 user with the exact `legacy_wp_user_id` and an active V2 restaurant with the exact `legacy_wp_id`. The legacy post status is preserved in the audit but does not invalidate an already active exact V2 restaurant. Missing or deleted V2 records are reported and excluded; names and slugs are never fallback matches.

## Deferred
- No mass user migration, final email provider, profile module or final administration design is included in this phase.
