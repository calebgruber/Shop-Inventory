# Theatre Shop Management

A Laravel 12 / PHP 8.3+ show-centered shop application for lighting, audio, rigging, and production equipment. There are no clients, rentals, quotes, invoices, or ERP workflows. **All production PHP dependencies are included in `vendor/`**, including Laravel, Fortify, Livewire, WebAuthn, and the PDF libraries. Upload the complete application to cPanel; no Composer or Node.js command is required on the host.

## Laravel, authentication, and live updates

Laravel owns HTTP routing, encrypted file-backed sessions, CSRF middleware, Eloquent user authentication, Gates, view rendering, database connections, migrations, logging, mail transports, and Artisan commands. Fortify's authentication pipeline handles password sign-in without replacing the local Tabler interface. Existing Argon2id hashes, lockouts, append-only audit history, password-reset links, and verified WebAuthn passkeys are retained. The existing theatre domain services and queries are preserved rather than risking historical inventory/revision data in a wholesale schema replacement.

Livewire 3 provides the header's **Live** indicator and a signed, authenticated update endpoint. It checks shop activity every 15 seconds while the page is active, refreshes visible AJAX inventory/order/repair tables and timeline results after changes, and refreshes the dashboard without a full reload. Searches, column choices, filters, and table pagination remain in place. The Live button checks immediately; disconnected/expired-session errors are visible. Background polling does not extend the idle session timeout. This is shared-host-friendly polling, not a WebSocket service: changes can take up to one polling interval to appear, and inactive/offline pages are not continuously refreshed.

Livewire and its bundled Alpine runtime are served from the local PHP package; do not load a second Alpine copy. Livewire 3's expression evaluation and dynamic styles require CSP `unsafe-eval` for scripts and `unsafe-inline` for styles. Inline scripts remain prohibited. The other CSP restrictions and HTTPS/session protections remain enabled. No Reverb, Horizon, Redis, Node.js, npm build, worker process, or paid broadcasting service is required.

## Included

- Shows, production contacts and dates, show workspaces, notes, files, activity history, and the eight show statuses.
- Original shop orders and unlimited **supplemental** revisions. Revision requests and line items are immutable, including database-level update/delete protection. Approval decisions are separate and final.
- Category → Subcategory → Item Type → Serialized Asset inventory, CSV uploads/pastes and serial-list imports, safe taxonomy management, and persistent drag ordering.
- Reusable packages with snapshotted component quantities, package/component return-date overrides, accessory ratios, advisory/strict rules, peak-demand planning, shortages, and scheduling conflicts.
- Barcode and manual pulls/returns, partial pulls, quantity limits, duplicate-scan prevention, serialized allocation in manual mode, and audited direct show-to-show transfers.
- Good, Broken, Missing Parts, and Missing returns; automatic repair tickets, repair history, bench-origin repairs, and missing-asset recovery.
- Asset lifecycle history and a filterable planning timeline. Future requests are shown at item-type level, not falsely assigned to specific serialized assets.
- AJAX tables with live search, exact-value filters, sorting, pagination, visible-column controls, and per-user saved views; permission-aware global search.
- Argon2id passwords, WebAuthn registration/authentication, single-use password resets, five roles, CSRF protection, session rotation/expiry, rate limits, account lockouts, and append-only audit/asset histories.
- Generated Thumbs-style SVG avatars from random stored seeds, regeneration overlays, and no profile-photo upload functionality.
- Light/dark branding, separate logos/backgrounds, favicon uploads, persistent workspace tabs, neutral surfaces, and random persistent card/working-tab accents matching the supplied layout.
- Real server-generated PDF receipts, show packets, pull/return/transfer sheets, repairs, inventory/missing/utilization/timeline/package/rule reports, and show archive books. Archive books append the original historical receipts and compatible uploaded PDF attachments, plus image/text attachments.

This is a new implementation, **not a production certification**. Validate the target MySQL 8 installation, cPanel mail delivery, actual WebAuthn devices, barcode hardware, permissions, backups, and large-report resource limits before handling live shop inventory. Local integration coverage uses MariaDB compatibility and synthetic signed WebAuthn ceremonies; it does not replace those deployment checks.

## Frontend assets

Tabler CSS/JavaScript are copied from the uploaded `User Interface/dashboard/dist` files to `public/assets/tabler`. No CDN Tabler or separate Bootstrap is loaded. The uploaded distribution and email examples remain unchanged and cannot be browsed publicly.

Under the revised asset requirement, only **Material Symbols Rounded** (Google Fonts) and pinned **SortableJS 1.15.6** (jsDelivr, with SHA-384 integrity) load from CDNs. There are no other icon libraries. Those two features require their CDNs to be reachable; a SortableJS loading failure displays an explicit notification and cannot silently change inventory ordering.

The password-reset email uses styles from the uploaded local Tabler typography email template. Remote font/import references are removed; the recovery body contains no externally hosted frontend dependencies.

## cPanel installation

1. Select PHP **8.3 or newer** and enable PDO MySQL, mbstring, OpenSSL, DOM/XML, fileinfo, GD, ctype, tokenizer, and sessions. Use MySQL **8+**, InnoDB, and utf8mb4. The database user needs schema-migration and `TRIGGER` privileges as well as normal CRUD permissions. PHP extensions are installed through cPanel's PHP selector, not by copying application files.
2. Upload the complete repository **including `vendor/`, `bootstrap/`, and hidden `.htaccess` files**. Dependencies are already installed and locked. There is no frontend build step. For future dependency maintenance on a development machine, use:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

   Commit/upload the updated `composer.lock` and `vendor/` together. Dependency updates are not performed by the public setup page. Bundled third-party packages retain their upstream license files.

3. Create an empty database and database user using cPanel's MySQL tools, and grant the user full access to that database. Enable AutoSSL so the application has HTTPS before installation. After configuring the document root and writable directories below, browse to `https://your-domain.example/setup` and follow the setup wizard: enter the database credentials and system email, create the first Super Admin, and the wizard will test the database, apply Laravel migrations, save private configuration, and create the administrator. The origin must match the domain open in the browser. The wizard refuses unrelated tables or existing user accounts. If setup is interrupted, revisit `/setup` over HTTPS to resume. Never reuse a database containing production or unrelated data.
4. Point the domain/subdomain document root to **`public/`**. Keep `app`, `config`, `database`, `resources`, `routes`, `storage`, `vendor`, and the uploaded source UI outside the document root. Apache must permit the supplied `public/.htaccess` and `mod_rewrite`.
5. If cPanel cannot point at `public/`, the repository-root `.htaccess` also supports a root installation. It routes to the public front controller and blocks private directories, dotfiles, source UI examples, CLI scripts, direct `.php` URLs, and direct `/public` access. Do not remove those rules. Verify them on your host.
6. Make `storage/` (including `storage/framework/cache`, `storage/framework/sessions`, and `storage/framework/views`) and `bootstrap/cache` writable by the PHP account, without using `777`. Make `config/` writable temporarily for setup, then remove its write permission afterward. Keep the rest of the application read-only to the web process where possible. The first request generates a private encryption key at `storage/framework/installation.key` with owner-only permissions; preserve it in backups and upgrades. Alternatively set `APP_KEY` in a private `.env` file. Never regenerate a deployed key casually: existing encrypted sessions will become invalid.
7. The setup wizard applies the migrations and creates the first administrator. For upgrades and command-line recovery, back up the database and storage, then run:

   ```sh
   php artisan migrate --force
   php artisan migrate:status
   php artisan shop:create-admin shopadmin shopadmin@example.edu
   ```

   The administrator command prompts for a hidden password. It never installs default credentials. Run it from an interactive Terminal; passwords must have at least 12 bytes. Remove access to this command from ordinary operators.

8. Enable HTTPS using cPanel AutoSSL and force HTTPS at the domain level. Production PHP requests over plain HTTP are rejected. Cookies use `HttpOnly`, `Secure`, `SameSite=Strict`, host-only scope, and `/`. Do not deploy the development configuration to production.
9. Sign in at `/login`, configure branding and pull/return modes under `/settings`, create users, build the inventory hierarchy, import serialized assets, and create a show.
10. Add a daily cPanel cron job, using your host's PHP binary and absolute application path:

    ```sh
    php /home/ACCOUNT/theatre-shop/artisan schedule:run
    ```

    Run the scheduler cron every minute. Laravel schedules authentication cleanup daily; it never deletes audit history. Old `migrate.php` and `console.php` commands remain compatibility wrappers.

### Upgrading an existing installation

Back up the database, private configuration, `storage/uploads`, `storage/pdfs`, and encryption key before replacing files. Keep `config/local.php`; existing settings, identifiers, passkeys, passwords, assets, PDFs, and immutable history do not need to be reimported. Upload the new application and bundled `vendor/`, remove stale files from the old `vendor/` during deployment, then run `php artisan migrate --force`. Do not run `/setup` against an existing installation. Existing native-PHP sessions are intentionally invalidated, so users sign in again.

The original migrations remain byte-for-byte unchanged under `database/legacy/` and keep their original `migrations` checksum/checkpoint ledger. A native Laravel migration adopts that schema without dropping/recreating data; Laravel records future migrations separately in `laravel_migrations`. Existing triggers and historical PDFs remain intact. Do not edit the legacy migrations or use `migrate:fresh`, `migrate:refresh`, or destructive rollback commands on live shop data.

Standard Laravel `.env` configuration is supported for terminal-based installs; `config/local.php` takes precedence for wizard-managed settings. Do not run `config:cache` before setup. After setup or configuration changes, use `php artisan optimize:clear`; optionally cache configuration only after confirming the installation works. Never upload a development machine's generated `bootstrap/cache` files.

Mail uses Laravel's synchronous sendmail transport by default. Configure authorized cPanel mail delivery, or set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and optionally `MAIL_SCHEME=smtps` in the private `.env`. Email styling remains locally sourced.

## Workflow semantics

An original order requests its equipment once. Each revision is a **new additional equipment request**, not a replacement of the original order. This avoids silently cancelling equipment that was already requested or pulled. Each revision has independent dates, approvals, line items, pulls, history, and receipts. Rejected requests release their planning commitments; finalized approval decisions cannot be rewritten.

Packages expand at request creation. Editing shop rules later does not rewrite the saved request or its stored PDF, although the current validation panel and subsequent approval/pull operations use the current rules.

Show production dates are chronological. Once orders exist, their schedule is a historical snapshot: changing equipment dates requires explicit dates on a new revision rather than silently moving existing requests. Complete/archive is blocked while assets remain assigned.

Manual quantities allocate deterministic, available serialized assets; they do not create anonymous inventory counts. For a damaged/missing return that must identify a specific unit, use barcode mode. Manual returns select earliest-due assignments first and apply the selected condition to that operation's quantities.

Prep and return dates are inclusive for future planning. Peak demand is calculated across overlapping intervals, not by summing all requests in a range. Repair/missing assets are excluded from serviceable totals. Overdue assignments remain committed until resolved instead of becoming falsely available at their old due date.

A transfer must satisfy a destination request and cannot exceed its remaining quantity. A user with both transfer and order-approval permission can transfer into a pending request and approve it atomically after releasing the source commitment. This permits transfers when the source holds all stock. Other transfer-authorized users need an already approved destination.

Barcode scanners should operate as keyboard wedges and end barcodes with Enter. The dedicated station captures scans without a text-box click; scans are serialized and must finish saving before the next scan. Typing into condition notes is not treated as scanner input. Fullscreen is optional.

## PDFs, files, and backups

PDFs are generated using Dompdf, not browser print styles. They include branding, timestamps, user attribution, headers/footers, and page numbering. Every stored PDF has a SHA-256 integrity hash and a download audit event. Download historical versions from the receipt table, rather than regenerating an old record when you need its original documentation.

Archive books retain the original stored order/revision/operation/repair PDFs, including the source side of transfers. Compatible attached PDFs are appended using FPDI. Encrypted PDFs and unsupported compressed-object PDFs are rejected during upload; export an unencrypted, flattened PDF 1.4-compatible file. Archive generation fails explicitly if a historical receipt or attachment is missing.

Uploads allow PNG/JPEG/WebP, PDF, text, and CSV. Branding accepts only raster images; use PNG for the favicon. SVG, executable files, oversized uploads, and profile-picture uploads are not accepted. Files are stored under unpredictable private filenames; show downloads require authentication and authorization.

Back up **both the database and `storage/` together**. Restoring only one can break receipt integrity and archive generation. Audit/asset histories are append-only through both application code and database triggers. The application does not claim cryptographic protection against a DBA who can drop those triggers.

## Roles

| Role | Access |
| --- | --- |
| Super Admin | All administrative and operating actions |
| Shop Manager | Shows/orders/approval, inventory, pulls/returns/transfers, repairs, reports, user directory |
| Inventory Manager | Show viewing, inventory management, pulls/returns/transfers, repairs, reports |
| Shop Staff | Show/inventory viewing, pulls/returns, repairs, reports |
| Read Only | Show/inventory viewing and reports |

All authenticated users can manage their own avatar, password, and passkeys. Passkey registration/revocation requires sign-in within the last ten minutes; user verification is required for WebAuthn ceremonies. Permissions are checked server-side, including table/search APIs. The permission map lives in `app/Middleware/Permissions.php`.

## Migrations and local verification

New Laravel migration files live in `database/migrations`; original checksummed statements live in `database/legacy`. A database advisory lock and replay-safe statement checkpoints protect initial schema adoption. Add new Laravel migrations for updates rather than editing the originals. MySQL DDL commits implicitly; take a backup before applying changes.

```sh
php tests/run.php
php tests/framework.php
```

For integration coverage, configure an **empty disposable** database with a name ending in `_testing`, run `php artisan migrate --force`, then:

```sh
php tests/run.php --integration
```

With the same populated test database and local development server running, `php tests/framework.php --integration` exercises real HTTP login/CSRF, Livewire signed updates, role restrictions, workspace navigation, binary PDFs, and logout replay protection. This HTTP check only accepts localhost URLs and requires the fixtures created by `tests/run.php --integration`.

Integration tests intentionally create fixture users, shows, assets, operations, and PDFs in that database/storage. Never point them at production. Use separate test storage/application copies and discard only those fixtures afterward.

For local development only, explicitly set `environment` to `development` and `url` to `http://localhost:8080` in your untracked configuration, then:

```sh
php -S localhost:8080 -t public tests/router.php
```

The development router is not a production server and is blocked by the production Apache rules.

## Structure

`bootstrap/app.php` configures Laravel; `artisan` provides its CLI. `app/Controllers`, `app/Models`, `app/Services`, `app/Http/Middleware`, and `app/Livewire` contain the application. `resources/views` holds native PHP Laravel views and Blade Livewire components. `routes/web.php` defines clean Laravel routes; `routes/console.php` defines administrative commands and scheduling. `config/` holds framework settings and private overrides; `database/migrations/` owns schema evolution; `public/` contains the front controller and served assets; `storage/` contains private uploads, PDFs, encrypted sessions, compiled views, cache, logs, and the encryption key.