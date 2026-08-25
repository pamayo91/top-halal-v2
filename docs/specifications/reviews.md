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
- JSON-LD AggregateRating/Review is deferred and must be emitted only for publicly visible approved V2 reviews.
