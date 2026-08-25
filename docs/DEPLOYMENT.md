# Deployment — Preproduction & Production

## Principles
- No Docker.
- Deploy preproduction over SSH using Git directly on the server; rsync is not the primary deployment method.
- Apache DocumentRoot must point to Laravel's `public/` directory.
- The Git checkout must live outside any publicly accessible directory.
- Never expose project root, `.env`, `storage`, legacy dumps, SSH material or Git metadata through the web server.
- Production/preproduction secrets live in server-side environment configuration, never Git.
- Do not deploy to production until the production cutover plan is explicitly approved.

## Git Strategy
- Remote repository: private GitHub repository at `git@github.com:pamayo91/top-halal-v2.git`.
- `main`: stable branch for future production-ready code.
- `develop`: preproduction integration branch.
- `feature/...`: temporary branches for significant work; merge into `develop` only after validation.
- Codex workflow: edit code, run targeted local tests, commit, push to GitHub, SSH to preproduction, pull `develop`, run deployment steps, then validate preproduction with browser/Playwright.

## Required Manual GitHub Actions
- Add the generated preproduction deploy public key to the private GitHub repository as a read-only deploy key.
- Confirm whether branch protection is desired for `main` and `develop`.
- Confirm who may merge `develop` into `main`; production promotion must stay explicit.

## Target deployment flow
1. SSH to preproduction using the saved alias `top-halal-preprod`.
2. Enter the non-public application directory.
3. `git fetch --prune` and update the working tree from `origin/develop`.
4. `composer install` when dependencies changed; use the preproduction-safe flags decided after server audit.
5. Run Laravel migrations when needed, with preproduction safeguards.
6. Build frontend assets when needed.
7. Run Laravel optimization/cache commands appropriate for preproduction.
8. Restart queue workers when workers exist and code affecting jobs changed.
9. Run health check and targeted smoke/E2E tests against the preproduction URL.
10. If validation fails, stop and fix before considering deployment complete.

## Future Automation
Create `scripts/deploy-preprod.sh` after the server audit confirms final paths, PHP/Composer locations, Node/npm needs, worker manager and Apache setup. The script should automate the target deployment flow without embedding secrets.

## Preproduction GitHub Deploy Key
- Key location on preproduction: `~/.ssh/top-halal-v2-github-deploy`.
- Public key generated: yes.
- Private key handling: remains only on the preproduction server and must never be committed or displayed.
- GitHub status: pending manual addition to the private repository deploy keys.

## Apache Layout
- Application path: to be confirmed by server audit; must be outside DocumentRoot.
- Apache DocumentRoot: must point only to the Laravel `public/` directory.
- `.env`, database dumps, uploads awaiting migration, SSH keys, API keys, passwords and Git metadata must never be web-accessible.

## Cron
Laravel scheduler should have one server Cron entry (exact path/user decided after server audit), calling `php artisan schedule:run` every minute.

## Queue worker
Use systemd or Supervisor after server audit. Do not rely on a browser request to process mail/AI queues.
