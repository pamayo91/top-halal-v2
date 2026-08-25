# Legacy files

`redirects.htaccess` is included because it contains only the historical redirect rules supplied for migration.

Place private legacy data here **locally only**, for example:
- `meyo5199_th.sql.gz`
- legacy uploads/media if needed

The `.gitignore` excludes SQL dumps and private legacy directories. Before every commit, verify no private legacy data is staged.
