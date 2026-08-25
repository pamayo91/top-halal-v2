# Start Top-Halal V2 in Codex

## 1. Put this starter in a new local project folder
Keep the private WordPress dump outside Git, then copy/place it at:
`legacy/meyo5199_th.sql.gz`

Do **not** commit it.

## 2. First run the read-only server audit on preproduction
Copy/run:
`bash scripts/server-audit.sh > server-audit.txt 2>&1`

Review the output before installing anything.

## 3. Initialize Git (if not already initialized)
```bash
git init -b main
git add .
git status
```
Confirm no SQL dump, `.env`, password or secret appears in staged files, then:
```bash
git commit -m "chore: initialize Top-Halal V2 project specs"
```
Remote GitHub/GitLab setup can be done after this; there is no need to learn advanced Git first.

## 4. First Codex task
Use the prompt in `docs/FIRST_CODEX_TASK.md`.

## 5. Important
Codex should not start the final homepage/design before:
- server audit is understood;
- Laravel project is bootstrapped;
- legacy DB can be queried read-only;
- migration inventory is reproducible;
- redirect source is inventoried/testable.
