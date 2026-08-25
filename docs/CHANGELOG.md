# Changelog

## 2026-08-25
- Documented the Git branch strategy and SSH/Git-based preproduction deployment workflow.
- Added French deployment summary in `docs/DEPLOIEMENT.md`.
- Updated project status for partial SSH access and pending private GitHub repository setup.
- Configured local Git `origin` for the private GitHub repository and generated a dedicated preproduction GitHub deploy key.
- Created local `main` and `develop` branches; remote push is pending GitHub SSH authorization for this workstation.
- Ran the real preproduction server audit and saved the consolidated result to `docs/generated/server-audit.txt`.
- Recorded PHP 8.4/Composer extension blockers and the preproduction GitHub SSH alias status.
- Generated a dedicated Codex workstation SSH key and configured the local Git remote through the `github-tophalal-codex` alias.
- Pushed local `main` and `develop` branches to GitHub after write access was enabled.
- Re-ran the PHP 8.4/Composer audit after extension changes and confirmed required Laravel bootstrap extensions are available.
- Generated the Laravel 13 application with the official Composer `create-project` workflow on preproduction using explicit PHP 8.4.
- Added MariaDB and read-only legacy WordPress connection placeholders, `/health`, a neutral Blade homepage, PHPUnit coverage and Playwright smoke-test scaffolding.
- Added `legacy:inventory` for safe aggregate legacy WordPress inventory reports.
- Re-ran the `.htaccess` inventory and confirmed the parser matches all active rule lines in the supplied file.

## 2026-08-24
- Created initial Codex handoff/starter documentation.
- Added project-level agent constraints and token-efficiency rules.
- Added server audit utility.
- Added legacy `.htaccess` redirect inventory utility and source file.
- Recorded the preproduction audit and official Composer bootstrap as hard prerequisites before Laravel initialization.
