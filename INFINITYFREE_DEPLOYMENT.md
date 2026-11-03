# SMARTJOB deployment: XAMPP and InfinityFree

This project remains a single PHP application with its existing `index.php?page=...` routes and PDO database layer. Deployment is manual/dry-run by default; automatic deployment on `main` pushes is gated off until a maintainer enables it after the first verified live deployment. No production deployment or production database migration was run as part of this change.

## Important credential response

Production credentials and provider keys have been present in local configuration or shared conversation content. Treat any such values as exposed: rotate the database password, application key, Gemini/OpenAI keys, mail/SMS credentials, and FTP password before deployment. Do not copy current local `.env` values into a public repository or use them in a deployment test. The repository ignores `.env` and `.env.*`; continue to keep real environment files outside Git.

## Environment configuration

1. Keep XAMPP settings in ignored `.env` or `.env.local`; `.env.local` takes precedence when both exist. The application loads one selected environment file rather than merging files with conflicting values.
2. The production website hostname selects `APP_ENV=production` and `.env.production`. The application first looks for `../.env.production` outside the web root, then checks the application root. If the host supports setting an absolute `SMARTJOB_ENV_FILE`, that can select another file; InfinityFree support for custom PHP environment variables has not been verified.
3. Use `.env.production.example` as the template. It contains the supplied production hostname, database name, and username, but no password or application key. Set the database password and unique random key privately, and set `SMARTJOB_PRIVATE_STORAGE` to a verified absolute writable path outside the public directory. Production startup now fails closed if database settings, `APP_DEBUG=false`, the key, or private storage are missing.
4. Prefer a private environment file outside the web root or platform-provided process variables. If neither is available and the configuration must be in the web root, first upload only a dummy file with no secrets and request `/.env.production` over HTTPS. Confirm the host returns an access-denied/not-found response with no file contents. Only then add `SMARTJOB_PUBLIC_ENV_FILE_ACCESS_VERIFIED=true` and replace the dummy with the real private configuration. Startup checks this acknowledgment and the repository `.htaccess` rules, but only a live HTTP request can verify that the host honors those rules.
5. Never commit production `.env` files. `.gitignore` excludes `.env` and `.env.*`; the deployment package excludes them too. Do not copy production credentials into GitHub Actions variables or the workflow.
6. Disable public registration until the production deployment checklist below passes. Configure verification providers first.

Generate a key locally without printing or storing it in this repository:

```powershell
C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Paste the generated result directly into the private host environment file. Do not reuse the development key.

## Production storage

The credential, company-document, and profile-photo services retain their random storage keys and server-side authorization checks. Configure `SMARTJOB_PRIVATE_STORAGE` to a writable path outside the host's public web directory. The default derives a sibling private directory from the application location; verify the resolved path and permissions on the hosting account rather than assuming it is writable. Never fall back to a public uploads folder. If the host cannot provide private writable storage, do not enable credential/document uploads.

Check `upload_max_filesize`, `post_max_size`, and `max_file_uploads` in the host's PHP selector/control panel. A deployment may need lower `SMARTJOB_COMPANY_DOCUMENT_MAX_FILE_BYTES` and `SMARTJOB_COMPANY_DOCUMENT_MAX_BATCH_BYTES` values; the application's server-side validation remains authoritative. DOCX verification requires ZIP support. Outbound API calls need cURL or HTTPS streams with OpenSSL and `allow_url_fopen`; hosting egress may still be restricted.

## Local preparation and packaging

Use the PHP version selected for the host, at least PHP 8.2, and run tests against a local copy of the imported schema. Do not point local tests at production.

```powershell
cd C:\xampp\htdocs\SMARTJOB
composer validate --no-check-publish
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
Get-ChildItem app,routes,config,scripts -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
& C:\xampp\php\php.exe -l index.php
Get-ChildItem tests -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe $_.FullName }
```

The workflow builds Composer production dependencies and packages only `app`, `assets`, `routes`, `config`, `vendor`, `index.php`, and `.htaccess`. The `config` PHP files are required by account-verification settings at runtime and are denied direct web access by `.htaccess`. Do not package `.env*`, `private`, uploads/storage, `.git`, tests, local backups, logs, or database dumps. Upload only into the verified web directory shown in the hosting control panel; the workflow rejects an FTP path that does not end in `/htdocs/`.

## Database backups and migrations

1. Before every schema change, use phpMyAdmin **Export → Custom → SQL** to save a full production database backup locally. Protect the backup as sensitive data; do not upload it to the website or commit it.
2. Confirm the selected production database name in phpMyAdmin, then compare `information_schema.tables` and `information_schema.columns` with the migration's requirements.
3. Apply only migrations that are not already represented in the production schema, in dependency order, using phpMyAdmin's SQL import/editor. Existing migration files may not be safe to run twice; do not blindly re-import them.
4. Never import `database/smartjob_db.sql` over the existing production database. It is a fresh-install schema, not a production upgrade.
5. Verify the migration's new columns/tables and representative row counts before deploying code that depends on them. Keep the pre-migration backup until production smoke tests pass. Restore only with an explicit incident/recovery decision.

There is no public migration route or automatic schema mutation. InfinityFree shell, SSH, scheduled jobs, and server-side Composer availability are not assumed.

## Manual FileZilla deployment

1. In the InfinityFree control panel, confirm the account's PHP version, document root, database host/name/user, FTP host/user, and whether explicit FTPS is supported. Do not use credentials copied from another account or pasted into source files.
2. Create and test the private storage directory first. Record its absolute path for `.env.production`; ensure it is not inside the document root and is writable by PHP.
3. Configure the private environment file and database from the hosting control panel. Do not upload `.env.production.example` as the live `.env.production` without filling and checking it privately.
4. In FileZilla, use the exact host/user/port from the FTP panel and choose explicit FTPS when the host supports it. Confirm the remote directory is the account's web root (often named `htdocs`, but verify it).
5. Upload the clean package files. Do not delete remote files to make the directory match; preserve existing private storage and user uploads. Do not upload the database dump, `.env`, backups, or secrets.
6. First test the HTTPS landing page and protected paths. Requests for `/.env`, `/.env.production`, `/app/`, `/config/`, `/database/`, `/tests/`, `/vendor/`, `/scripts/`, and composer metadata must not disclose file contents. Confirm a direct PHP file placed in a public uploads folder cannot execute; remove that test file immediately.
7. Verify login, logout, CSRF-protected actions, administrator access, student exploration/application, employer verification/posting, private document authorization, and account notifications. Confirm unauthorized roles receive an access denial.
8. Test file upload/download using a non-sensitive test account and verify stored files are not reachable at a direct public URL. Delete the test record/file through the application when appropriate.
9. Send a verification email/SMS or test an AI API only after explicit authorization, with an account and content intended for that test. Confirm the provider is reachable; do not expose credentials in browser output or logs.
10. Keep public registration disabled until the security and application checks pass.

## Optional manual GitHub workflow

The workflow at `.github/workflows/deploy.yml` supports manual dispatch and a gated `main` push trigger. Manual dispatch defaults to dry-run. Push-triggered deploys remain disabled unless the GitHub repository variable `ENABLE_AUTO_DEPLOY` is set to `true`; do not set it until the first live deployment and smoke tests are successful. Protected GitHub Environment reviewers remain an additional approval gate.

Before using it:

- Configure the GitHub Environment `infinityfree-production` with required reviewers.
- Add `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, and `FTP_REMOTE_DIR` as environment secrets.
- Set repository variables `FTP_PROTOCOL` to `ftps` or `ftp` as confirmed by the host, and optionally set `FTP_PORT`.
- Confirm `FTP_REMOTE_DIR` is the exact web root shown in the InfinityFree FTP panel and ends in `/htdocs/`; do not guess the path or point it at private storage.
- Run with **dry_run=true** first and inspect the action summary. Only after comparing the proposed destination and exclusions should an authorized maintainer manually dispatch with `dry_run=false`.
- After the first real manual deployment, complete the production checklist below before setting `ENABLE_AUTO_DEPLOY=true`.
- If FTP fails, verify host, port, account status, protocol, remote directory, and provider limits in the hosting panel. Do not enable deletion or retry with broader paths.

This workflow is a deployment convenience, not proof that InfinityFree allows the selected protocol or outbound provider calls.

## Preflight and host limitations

Run the local read-only configuration diagnostic first. It reports only the selected environment filename and database host/port, never the password or application key, and does not connect to MySQL:

```powershell
C:\xampp\php\php.exe scripts\deployment-preflight.php --config-only
```

Run the full deployment check only after confirming its reported database host is a local XAMPP address. It inspects the database/schema and storage without printing credentials:

```powershell
C:\xampp\php\php.exe scripts\deployment-preflight.php
```

It reports extension availability, database connectivity, required schema elements, and private storage readiness without printing credentials. The local XAMPP result does not certify the production host. Re-run it only in a private CLI-capable hosting environment; do not publish it as a web endpoint.

InfinityFree account-specific PHP version/extensions, FTP mode/path, outbound SMTP/API access, upload limits, filesystem and PHP session permissions, `.htaccess` overrides, and actual production HTTPS behavior cannot be confirmed from this local workspace. The workflow installs Composer dependencies before upload, so SSH and host-side Composer are not required. The existing routes use `index.php?page=...` and do not require URL rewriting. Verify that HTTPS sets the `smartjob_session` cookie with `Secure`, `HttpOnly`, and `SameSite=Lax`, and test the main route/query-string pages on the live host. Verify provider reachability with approved test messages only; do not assume SMTP, Mailjet, Gemini, or other outbound requests are allowed. Do not leave diagnostic scripts or test files publicly accessible.

## Deployment checklist

| Check | Local evidence | Production status |
|---|---|---|
| PHP 8.2+ and required extensions | Run preflight and tests | Verify in hosting panel; not remotely verified |
| Production configuration and key are private | `.env.production.example` contains placeholders only; `.env*` ignored | Set rotated values privately; not deployed |
| PDO MySQL uses UTF-8 and Asia/Manila time | `CoreDatabaseTest.php` (blocked: local MySQL is not listening) | Re-run preflight against production only from private CLI |
| Database backup and incremental migration | Documented above; no production DB changed | Take backup and manually compare/apply needed migrations |
| Private uploads outside the document root | Storage classes retain validation and authorization | Set path and verify on host before enabling uploads |
| `.htaccess` denies secrets and private files | Rules updated; Apache behavior not emulated locally | Request-test all protected URLs on host |
| Authentication, CSRF, RBAC, application modules | Existing test suite available; full local run not verified in this session | Smoke-test role-by-role after upload |
| Email, SMS, Gemini and OpenAI integrations | Existing mocked/provider-configuration tests | Reachability and live sends not tested |
| GitHub deployment | Manual dry-run by default; automatic push deployment gated by `ENABLE_AUTO_DEPLOY` | Not activated; requires configured secrets and approval |
| Production application data preserved | No production connection or migration was made | Confirm counts after backup and deploy |
