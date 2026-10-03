# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

InternTrack is a Vietnamese-language internship management app (roles: student, company, lecturer, admin). Plain PHP 8.2 + PDO/MySQL on Apache/XAMPP, no framework, no build step, no test suite or linter. `README.md` (Vietnamese) has full setup, OTP/Gmail and production notes.

## Commands

- Install deps (PHPMailer only): `composer install`
- Run: start Apache + MySQL in XAMPP, open `http://localhost/InternTrack/`
- DB setup: create DB `interntrack` (utf8mb4_unicode_ci), import `database/interntrack.sql` (idempotent `CREATE TABLE IF NOT EXISTS`), then optionally `database/seed.sql` (local demo accounts only). Apply `database/migrations/*.sql` manually, in date order, to older databases.
- Syntax check a file: `C:\xampp\php\php.exe -l <file>`
- Config comes from env vars or a git-ignored `.env` (see `.env.example`): `DB_*`, `MAIL_*`. Read via `app_env()` in `config/app.php`.

## Architecture

Single front controller: everything goes through `index.php`.

- **Routing**: `?page=<role>/<screen>` must be in the whitelist `$availablePages` in `index.php`; adding a screen means adding it there, plus a view under `views/<role>/`, plus an entry in `views/layouts/page-data.php`. The role prefix must match the user's role (else 403); `auth/*` are the only public pages.
- **POST flow**: `index.php` checks CSRF (`app_valid_csrf`), handles auth/OTP actions inline, and delegates all other `action` values to `handle_workspace_action()` in `controllers/ActionController.php`, which does role checks (`action_user`), validation (`action_value`), DB writes, and returns a redirect page. User-facing errors are thrown as `DomainException` (shown as a flash); any other `Throwable` is logged and replaced by a generic message. Post/redirect/get with `app_set_flash`/`app_take_flash`.
- **GET flow**: `?download=<type>&id=<n>` is served by `serve_workspace_download()` (permission-checked); otherwise the view is `require`d. Files in `uploads/` are blocked by `.htaccess` and must only be served through that endpoint.
- **Views are data-driven**: each role view calls the shared `views/layouts/screen.php`, which loads a per-screen config from `views/layouts/page-data.php`, hydrates it with live DB data via `load_screen_data()` in `controllers/PageController.php` (queries via `page_all`/`page_one`/`page_count`), and renders through `header.php`/`content.php`/`footer.php`. Look at `page-data.php` + `PageController.php` + `content.php` together to change a screen.
- **Auth**: `controllers/AuthController.php` (session login, password-reset OTP stored as SHA-256 hash, 10-min expiry, 5 attempts, 60s resend). Mail is sent via PHPMailer in `app_send_password_reset_otp()` (`config/app.php`).
- **Data access scoping**: internship access is scoped per role by `action_internship()` (student/company/lecturer id on `internships`); reuse it rather than writing ad-hoc ownership checks.
- `models/*.php` and `controllers/logout.php` are empty placeholders; SQL lives in the controllers.
- `config/app.php` installs a global error handler that converts PHP warnings/notices into `ErrorException`, so sloppy code throws.
- Uploads: `store_uploaded_file()` validates real MIME and size (CV/report up to 5 MB, avatars 3 MB) into `uploads/<folder>/`.

## Conventions

- `declare(strict_types=1)` at the top of PHP files; UI strings and comments are in Vietnamese; escape output with `screen_escape()`.
- Always use PDO prepared statements via `database()` (`config/database.php`).
