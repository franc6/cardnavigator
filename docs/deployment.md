# Deployment

Two GitHub Actions workflows are provided: one for cPanel hosting and one for any server accessible via SSH. Both trigger on push to `main`, build the project locally, and upload the result.

---

## Prerequisites

- PHP 8.4+ and Composer on the GitHub Actions runner (handled automatically by the workflow)
- Node.js 22+ (handled automatically)
- A cPanel-managed host **or** any host reachable by SSH

### PHP extensions on the server

Required: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `curl`, `gd`.

Optional: `imagick` (with libheif support) — enables HEIC/HEIF uploads. Without it the app still accepts PNG, JPEG, WEBP, and GIF, and politely rejects HEIC uploads.

---

## How path splitting works

The app files and the web-accessible public files live in two server directories. Two secrets control the locations:

| Secret | Example value | What it points to |
|--------|--------------|------------------|
| `APP_DIRECTORY` | `cardnavigator` or `apps/cardnavigator` | Laravel app root (routes, controllers, vendor, …) |
| `DOCUMENT_ROOT` | `public_html` or `public_html/myapp` | Web server document root (index.php, built assets) |

Both paths are **relative to a common parent directory** (the cPanel home directory for cPanel, the SSH user's working directory for SSH). They may contain multiple path components.

`DOCUMENT_ROOT` is **optional**. If you leave it unset, it defaults to `${APP_DIRECTORY}/public` — the standard Laravel layout where the `public/` directory sits inside the app root. Set `DOCUMENT_ROOT` only when the web server's document root lives elsewhere (typical on cPanel where `public_html` is separate from your app directory).

During deployment, `public/index.php` and `bootstrap/app.php` are patched with relative `../` paths computed from the depth of each secret so that PHP can locate the app root from the document root and vice versa — no hardcoded absolute paths are used. The patches are skipped when `DOCUMENT_ROOT` resolves to `${APP_DIRECTORY}/public`, because Laravel's stock relative paths already work in that layout.

---

## Repository secrets

### cPanel workflow

| Secret | Description |
|--------|-------------|
| `SERVER` | Hostname of the cPanel server |
| `CPANEL_USERNAME` | Your cPanel username |
| `CPANEL_API_KEY` | cPanel API token (see below) |
| `APP_DIRECTORY` | App path relative to home, e.g. `cardnavigator` |
| `DOCUMENT_ROOT` | *(optional)* Web root relative to home, e.g. `public_html`. Defaults to `${APP_DIRECTORY}/public` |

To generate a cPanel API token: log in to cPanel → **Security** → **Manage API Tokens** → **Create**.

### SSH workflow

| Secret | Description |
|--------|-------------|
| `SERVER` | Hostname of the server |
| `SSH_USER` | SSH login username |
| `SSH_PRIVATE_KEY` | PEM-format private key (see below) |
| `APP_DIRECTORY` | App path relative to the SSH working directory |
| `DOCUMENT_ROOT` | *(optional)* Web root relative to the SSH working directory. Defaults to `${APP_DIRECTORY}/public` |

To generate a key pair:
```bash
ssh-keygen -t ed25519 -C "cardnavigator-deploy" -f ~/.ssh/cardnavigator_deploy
```
Add the **public** key (`~/.ssh/cardnavigator_deploy.pub`) to `~/.ssh/authorized_keys` on the server. Add the **private** key (`~/.ssh/cardnavigator_deploy`) as the `SSH_PRIVATE_KEY` secret.

---

## cPanel deployment

### What the workflow does

1. Enters maintenance mode by uploading `storage/framework/maintenance.php` (the app returns 503 for all requests during deployment).
2. Fetches the old file manifest from the server.
3. Deletes the `vendor/` directory so it is always deployed fresh.
4. Uploads all app files (excluding `public/`) to `APP_DIRECTORY`.
5. Uploads all public files to `DOCUMENT_ROOT`.
6. Uploads updated file manifests.
7. Deletes any files present in the old manifest that are absent from the new one (stale file cleanup).
8. If `.env` is absent on the server, creates one from `.env.example`, generates an `APP_KEY`, and uploads it.
9. Bootstraps `database/database.sqlite` if absent.
10. Exits maintenance mode (runs even if a previous step failed).

> **Note:** The cPanel workflow cannot run `php artisan` commands on the server. Run migrations and cache commands manually via the cPanel terminal or the app's admin database page after the first deploy.

---

## SSH deployment

### What the workflow does

1. Enters maintenance mode via `php artisan down`.
2. Fetches old file manifests.
3. Deletes `vendor/` on the server.
4. Uploads app files (tar over SSH).
5. Uploads public files (tar over SSH).
6. Uploads updated manifests.
7. Deletes stale files.
8. Bootstraps `.env` if absent.
9. Bootstraps `database/database.sqlite` if absent.
10. Runs `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`.
11. Exits maintenance mode via `php artisan up` (runs even if a previous step failed).

---

## First-time server setup

### cPanel

1. After the first successful workflow run, open the cPanel **File Manager**, navigate to `APP_DIRECTORY`, and verify `.env` was uploaded.
2. Visit `https://your-domain/admin/database` in the app to run any pending migrations.
3. Once you are satisfied the app works correctly, edit `.env` (cPanel File Manager or terminal) and change `APP_ENV=production`.
4. Open a cPanel terminal and run:
   ```bash
   cd APP_DIRECTORY && php artisan storage:link
   ```
5. Create the first admin user:
   ```bash
   cd APP_DIRECTORY && php artisan user:create --admin
   ```
   You will be prompted for a name, email address, and password. This account will have access to the admin panel. There are no default credentials.

### SSH

The SSH workflow handles `.env` and `database/database.sqlite` automatically on first deploy. After verifying the app works:

1. SSH into the server and edit `APP_DIRECTORY/.env`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   ```
2. Run:
   ```bash
   cd APP_DIRECTORY && php artisan storage:link
   ```
3. Create the first admin user:
   ```bash
   cd APP_DIRECTORY && php artisan user:create --admin
   ```
   You will be prompted for a name, email address, and password. This account will have access to the admin panel. There are no default credentials.

---

## Running migrations

Migrations run automatically on every deploy in the SSH workflow. For cPanel, visit the admin database page at:

```
https://your-domain/admin/database
```

Click **Run Migrations** to apply any pending schema changes. You can also run them from a cPanel terminal:

```bash
cd APP_DIRECTORY && php artisan migrate
```

---

## Maintenance mode

The site returns HTTP 503 to all visitors during deployment. If a workflow step fails, the final **Exit maintenance mode** step still runs (`if: always()`) so the site is never left permanently in maintenance mode.

---

## What is never overwritten

| File | Behaviour |
|------|-----------|
| `.env` | Skipped on upload if it already exists on the server |
| `database/database.sqlite` | Skipped on upload if it already exists on the server |

All other files are overwritten by each deployment. Files removed from the repository are deleted from the server (tracked via the `.deploy-manifest` file stored in each deployment directory).
