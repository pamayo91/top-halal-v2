# ListingPro Opening-Hours Audit

- Legacy listings: `7704`
- Detected opening hours: `0`
- Without detected opening hours: `7704`

## Formats

- `business_hours_null`: 3168; examples: `13453, 13455, 13456, 13457, 13458, 13459, 13460, 13461, 13462, 13463`
- `business_hours_empty`: 4536; examples: `13454, 14040, 14041, 14042, 14043, 14044, 14055, 14056, 14057, 14058`
- `business_hours_other`: 0; examples: ``

## Anomalies

- No non-empty ListingPro business_hours structure exists in the legacy listings.
- Time-like values outside a recognised hours path are not migrated; the audit found one in an email field and treats it as hostile legacy data.

No migration sample was selected because no legacy restaurant has parseable opening hours.
