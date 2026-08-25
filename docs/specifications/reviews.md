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
