# Legacy redirect inventory

Source: `legacy\redirects.htaccess`

## Summary

- **line count**: 499
- **redirect records**: 460
- **static exact**: 395
- **rewrite rules**: 65
- **with conditions**: 14
- **infrastructure rules**: 3
- **query condition rules**: 2
- **home destinations**: 32
- **drops query**: 60
- **unicode sources**: 4
- **normalized duplicate sources**: 1

## Potential normalized duplicate sources

- `/nouvelle-boucherie-halal-High-Wycombe` — lines 91 and 92

## Notes

- This is an inventory, not a semantic proof that each rewrite behaves exactly as intended.
- Conditional/regex rules must be validated with URL test cases before migration.
- Host/protocol rules should normally remain in Apache rather than the Laravel redirect manager.
