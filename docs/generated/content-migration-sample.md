# Content Migration Sample

- Articles: `27`, `104`, `295`, `10697`, `11461`.
- Pages: `4`, `5`, `38`, `10430`, `11554`.
- Both dry-run and two idempotent apply passes completed on preproduction.
- Database result: 5 articles, 5 pages, 0 residual Visual Composer shortcode rows, 0 duplicate legacy IDs.
- Page `38` exercised row, column, column text, raw HTML and sidebar conversion. Page `11554` covers the message shortcode. Page `10430` contained a legacy script, removed by default.
- Legacy scripts and non-allowlisted iframes are removed. The current embed allowlist is YouTube and Vimeo.
- SEO values from Yoast are persisted when available; no legacy JSON-LD is copied.
- Featured-image references are retained as legacy attachment IDs only; no WordPress upload bulk copy occurred.
- Playwright passed on all ten preproduction previews in desktop and mobile: HTTP 200, H1, clean console/network, no visible shortcode, no legacy script and no non-allowlisted iframe.
- The workstation uses Codex's bundled Node runtime at `C:\Users\pamay\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe`; it was absent only from the shell PATH.
