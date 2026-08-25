# Email Delivery Audit

Date: 2026-08-25

## Preproduction
- `APP_URL` is configured server-side; generated application links use HTTPS preproduction URLs.
- Laravel database queue and failed-job table exist. No failed job was reported during this audit.
- No credential or effective SMTP value is recorded in this report.
- The test command queues a message only; it does not reveal recipients, credentials or transport internals.

## DNS (read-only)
- SPF: `v=spf1 a mx ip4:109.234.162.90 -all` is published for `top-halal.fr`.
- DMARC: no `_dmarc.top-halal.fr` TXT record was detected.
- DKIM: no TXT record was detected for the generic `default` or `selector1` selectors. DKIM selectors are provider-specific, so the absence of these generic names does not prove no DKIM exists.

## Required before real sending
- Select an SMTP/API provider and publish its DKIM selector(s).
- Add a DMARC record, initially monitoring-only (for example `p=none`), then tighten after observing alignment.
- Validate the provider sending IP/domain against SPF before any campaign.
