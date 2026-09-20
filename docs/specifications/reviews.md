# Restaurant Reviews

Reviews are separate domain objects from editorial comments.

## Required data
- restaurant relation;
- user or author name/email;
- rating;
- optional title;
- content;
- moderation status;
- created/approved dates;
- legacy review identifier when migrated.

## Rules
- Moderation before public display unless a future policy changes it.
- Aggregate rating is calculated only from eligible public reviews.
- Structured data must match visible review/rating content.
- Support owner/admin response as a separate response object or explicit review-response relation.
- Legacy pilot: `rating` is an integer strictly in 1–5. Only approved V2 reviews contribute to the runtime aggregate (`count`, `avg`); no denormalized rating source of truth is stored.
- New submissions are pending, URL-free, CSRF/honeypot/rate-limit protected and escaped when rendered. `reviews:moderate` provides temporary approve/reject/spam/delete operations.
- A new or unproven review author first creates a `contribution_verifications` record, not a review. It carries a hashed random token, temporary signed URL, 24-hour expiry and the exact restaurant/payload. Only one successful click atomically creates/reuses the `User`, marks its e-mail verified and creates the review as `pending` with `user_id`.
- A signed-in user or an anonymous browser holding an unexpired server-side proof for that exact `User` creates a `pending` review directly. Merely knowing an existing or historically verified e-mail never bypasses this proof.
- Exactly one operational alert is queued for the configured Contact recipient when, and only when, a new review actually enters `pending`. A review awaiting e-mail confirmation generates no operational alert. The alert’s delivery-log reference is retained on the review as the idempotency key, so repeat confirmation links, repeated service calls and queue retries cannot create a second alert.
- Every review-submission outcome shown in the restaurant review section returns to the canonical restaurant URL with `#avis`. The review section has a stable `id="avis"` and an anchor scroll margin so the fixed-site chrome cannot hide its status message.
- After a valid review verification, the confirmation page says that the e-mail (not a generic identity) is confirmed, accurately states that the review awaits validation, and offers a server-derived `Retourner au restaurant` link to that restaurant’s `#avis` anchor. The following restaurant request shows the matching pending-moderation message and never repeats the request to verify the e-mail.
- A consumed review link is explicitly idempotent (`Cet avis a déjà été confirmé.`) and never creates another review. A target that is no longer publicly available is closed before contribution creation and has no resend action.
- An expired verification page offers one rate-limited resend only while the restaurant remains published and the identity is not disabled; it replaces the hashed token and does not create a review.
- A user with a current restaurant-management right cannot submit a review for that restaurant. The central review-eligibility rule reuses the represented-restaurant query: approved claim (including `new_submission`), exact `legacy_restaurant_authorships`, or an active non-rejected `restaurant_submission` until another approved claim supersedes it. A non-manager depositor therefore remains distinct from an owner and retains its temporary edit right, but cannot review its own submitted listing; it can review an unrelated listing. The rule is enforced again immediately before review creation, including after e-mail verification; the public form is hidden for a signed-in conflicted user. A refused direct POST creates neither review, verification, operational notification nor delivery log.
- The public review form has no title field. Existing historical `title` values remain stored and may be rendered when present; new review payloads intentionally omit it.
- The public form uses a native `fieldset` with five required 1–5 radio inputs, styled as stars without a JavaScript dependency. The source, keyboard and visual order are 1→5, and each selected value has the exact matching label: `1 Décevant`, `2 Moyen`, `3 Bien`, `4 Très bien`, `5 Excellent`. It labels the author field `Prénom ou pseudo`, keeps identity/e-mail fields in a two-column desktop grid (one column on compact screens), and reopens with the selected rating and field-local accessible errors after validation.
- Star icons are visually compact while retaining overlapping 44 px click/focus targets. Hover previews a continuous temporary visual score and matching label only; leaving the group restores the submitted radio value and its label.
- For an authenticated review author, the `Prénom ou pseudo` suggestion uses a recorded human claim/submission identity first. A restaurant-related account name is never assumed human; if no explicit human identity is available, the field is empty. New reviews render exactly the submitted author name.
- Published-review summaries show rounded stars, the approved-only average with at most one useful decimal, and the correctly singularized review count. Historical titles remain visible only when non-empty; title-less reviews reserve no title space.
- JSON-LD AggregateRating/Review is deferred and must be emitted only for publicly visible approved V2 reviews.
## Signalement public sur fiche restaurant

Le bloc public `Une information à corriger ?` conserve son titre, son toggle `Signaler une erreur` et tout son traitement existant. Lorsqu’il est ouvert, son formulaire réemploie la carte compacte des avis (fond léger, bordure, rayon, champs, padding et action alignée à droite) et reste limité à la même largeur de lecture sur desktop. Sur mobile, il occupe la largeur disponible sans débordement et son action devient pleine largeur comme celle du formulaire d’avis.
