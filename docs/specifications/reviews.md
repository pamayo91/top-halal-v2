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
- An expired verification page offers one rate-limited resend only while the restaurant remains published and the identity is not disabled; it replaces the hashed token and does not create a review.
- A validated restaurant manager cannot submit a review for that restaurant. The single manager rule covers an approved claim (including `new_submission`) and an exact `legacy_restaurant_authorships` relation, but not a non-manager depositor. It is enforced again immediately before review creation, including after e-mail verification; the public form is hidden for a signed-in manager.
- JSON-LD AggregateRating/Review is deferred and must be emitted only for publicly visible approved V2 reviews.
