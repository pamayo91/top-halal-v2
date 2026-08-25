# Redirect Manager

## Goal
Move historical content redirects out of `.htaccess` into a manageable, testable and cached application system while leaving host/protocol/server rules in Apache.

## Source
`legacy/redirects.htaccess` contains the historical redirect rules supplied for migration.

## Types
1. Exact path redirect.
2. Pattern/regex redirect with captures.
3. Conditional/query-string redirect where needed.
4. Apache-only infrastructure rule (host/protocol/etc.) — not imported into application runtime.

## Redirect record
Suggested fields:
- id
- source pattern/path
- destination
- status code (initially primarily 301)
- match type
- query handling/preservation policy
- active
- origin (`htaccess`, `manual`, `migration`)
- original legacy rule (for traceability)
- hit_count
- last_hit_at
- timestamps

## Runtime
- Avoid a database query for every request.
- Build/cache efficient exact maps and a small pattern rule set.
- Rebuild cache when rules change.
- Prevent loops, self redirects and chains where possible.

## Administration
Search, create, edit, disable/delete, test a URL, import htaccess/CSV, export CSV, show hits/last hit.

## Import validation
Classify each source rule as:
- imported automatically;
- keep in Apache;
- requires review.
Normalize Unicode/percent-encoded path variants carefully.

## Fallback policy
Prefer exact relevant replacement, then relevant category/city, then homepage if no equivalent and product owner chooses that fallback.
