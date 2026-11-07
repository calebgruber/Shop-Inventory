# Theatre Shop Management

A PHP 8.3+ show-centered shop application for lighting, audio, rigging, and production equipment. There are no clients, rentals, quotes, invoices, or ERP workflows.

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

1. Select PHP **8.3 or newer** and enable PDO MySQL, mbstring, OpenSSL, DOM/XML, fileinfo, GD, and sessions. Use MySQL **8+**, InnoDB, and utf8mb4. The database user needs schema-migration and `TRIGGER` privileges as well as normal CRUD permissions.
2. Build the PHP vendor directory on cPanel Terminal or another machine using:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader
   ```

   Upload `vendor/` with the application if Composer is unavailable on the host. No Node.js, Redis, Docker, PM2, worker daemon, or custom production server is required.

3. Create an empty database and database user using cPanel's MySQL tools, and grant the user full access to that database. Enable AutoSSL so the application has HTTPS before installation. Browse to `https://your-domain.example/setup` and follow the setup wizard: enter the database credentials and system email, create the first Super Admin, and the wizard will test the database, apply migrations, save private configuration, and create the administrator. The origin must match the domain open in the browser. The wizard refuses unrelated tables or existing user accounts. If setup is interrupted, revisit `/setup` over HTTPS to resume. Never reuse a database containing production or unrelated data.
4. Point the domain/subdomain document root to **`public/`**. Keep `app`, `config`, `database`, `resources`, `routes`, `storage`, `vendor`, and the uploaded source UI outside the document root. Apache must permit the supplied `public/.htaccess` and `mod_rewrite`.
5. If cPanel cannot point at `public/`, the repository-root `.htaccess` also supports a root installation. It routes to the public front controller and blocks private directories, dotfiles, source UI examples, CLI scripts, direct `.php` URLs, and direct `/public` access. Do not remove those rules. Verify them on your host.
6. Make `storage/uploads`, `storage/pdfs`, and `storage/logs` writable by the PHP account, without using `777`. Keep the rest of the application read-only to the web process where possible.
7. The setup wizard applies the migrations and creates the first administrator. For upgrades and command-line recovery, back up the database and storage, then run:

   ```sh
   php migrate.php
   php migrate.php --status
   php console.php create-admin shopadmin shopadmin@example.edu
   ```

   The administrator command prompts for a hidden password. It never installs default credentials. Run it from an interactive Terminal; passwords must have at least 12 bytes. Remove access to this command from ordinary operators.

8. Enable HTTPS using cPanel AutoSSL and force HTTPS at the domain level. Production PHP requests over plain HTTP are rejected. Cookies use `HttpOnly`, `Secure`, `SameSite=Strict`, host-only scope, and `/`. Do not deploy the development configuration to production.
9. Sign in at `/login`, configure branding and pull/return modes under `/settings`, create users, build the inventory hierarchy, import serialized assets, and create a show.
10. Add a daily cPanel cron job, using your host's PHP binary and absolute application path:

    ```sh
    php /home/ACCOUNT/theatre-shop/console.php cleanup
    ```

    This removes expired authentication-rate-limit and reset-token records, not audit history.

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

Migration files live in `database/migrations`. A database advisory lock prevents concurrent runners. Applied filenames, checksums, statement checkpoints, and status are tracked in `migrations`; editing an applied migration is rejected. MySQL DDL commits implicitly, so statements must be replay-safe or use the runner's `exists_sql`/`exists_params` guard. Add a new migration for schema updates rather than editing existing ones. There is no destructive rollback command.

```sh
php tests/run.php
```

For integration coverage, configure an **empty disposable** database with a name ending in `_testing`, run migrations, then:

```sh
php tests/run.php --integration
```

Integration tests intentionally create fixture users, shows, assets, operations, and PDFs in that database/storage. Never point them at production. Use separate test storage/application copies and discard only those fixtures afterward.

For local development only, explicitly set `environment` to `development` and `url` to `http://localhost:8080` in your untracked configuration, then:

```sh
php -S localhost:8080 -t public tests/router.php
```

The development router is not a production server and is blocked by the production Apache rules.

## Structure

`app/Controllers`, `app/Models`, `app/Views`, `app/Services`, and `app/Middleware` contain the MVC application. `routes/web.php` defines clean routes. `config/` holds defaults and private deployment overrides; `database/migrations/` owns schema evolution; `public/` contains only the front controller and served assets; `resources/` is reserved for application resources; `storage/` contains private uploads, PDFs, and error logs.