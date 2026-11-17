<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="/assets/tabler/tabler.min.css">
    <link rel="stylesheet" href="/assets/css/setup.css">
</head>
<body class="setup-page">
    <main class="setup-shell">
        <header class="setup-heading">
            <div class="setup-mark">TS</div>
            <p class="setup-kicker">THEATRE SHOP MANAGEMENT</p>
            <h1>Let’s get your shop ready.</h1>
            <p>Connect your database, prepare the application, and create your first administrator.</p>
        </header>

        <?php if ($error): ?>
            <div class="alert <?= $ready?'alert-danger':'alert-warning' ?>" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($ready): ?>
            <form action="/setup" method="post" class="setup-form" id="setup-form">
                <?= csrf() ?>
                <section class="setup-card" aria-labelledby="database-heading">
                    <div class="setup-step">01</div>
                    <div class="setup-card-heading">
                        <h2 id="database-heading">Application &amp; database</h2>
                        <p>Create the MySQL database and user in your hosting panel first.</p>
                    </div>
                    <label class="form-label">Shop name
                        <input class="form-control" name="app_name" maxlength="160" value="<?= e($values['app_name']) ?>" required>
                    </label>
                    <label class="form-label">Secure site address
                        <input class="form-control" type="url" name="url" value="<?= e($values['url']) ?>" required>
                        <span class="form-hint">Use the HTTPS address currently open in your browser.</span>
                    </label>
                    <div class="setup-fields">
                        <label class="form-label">Database host
                            <input class="form-control" name="database_host" autocomplete="off" value="<?= e($values['database_host']) ?>" required>
                        </label>
                        <label class="form-label">Port
                            <input class="form-control" type="number" name="database_port" min="1" max="65535" value="<?= e($values['database_port']) ?>" required>
                        </label>
                    </div>
                    <label class="form-label">Database name
                        <input class="form-control" name="database_name" autocomplete="off" value="<?= e($values['database_name']) ?>" required>
                    </label>
                    <label class="form-label">Database username
                        <input class="form-control" name="database_username" autocomplete="username" value="<?= e($values['database_username']) ?>" required>
                    </label>
                    <label class="form-label">Database password
                        <input class="form-control" type="password" name="database_password" autocomplete="new-password" <?= $values['database_name']?'':'required' ?>>
                        <span class="form-hint">Used to test the connection and saved privately outside the public directory.</span>
                    </label>
                    <label class="form-label">System email address
                        <input class="form-control" type="email" name="mail_from" autocomplete="email" value="<?= e($values['mail_from']) ?>" required>
                    </label>
                </section>

                <section class="setup-card" aria-labelledby="admin-heading">
                    <div class="setup-step">02</div>
                    <div class="setup-card-heading">
                        <h2 id="admin-heading">Create your administrator</h2>
                        <p>This account receives full control. No default password is installed.</p>
                    </div>
                    <label class="form-label">Your name
                        <input class="form-control" name="admin_name" maxlength="160" autocomplete="name" value="<?= e($values['admin_name']) ?>" required>
                    </label>
                    <label class="form-label">Username
                        <input class="form-control" name="admin_username" maxlength="80" autocomplete="username" value="<?= e($values['admin_username']) ?>" required>
                    </label>
                    <label class="form-label">Email
                        <input class="form-control" type="email" name="admin_email" maxlength="254" autocomplete="email" value="<?= e($values['admin_email']) ?>" required>
                    </label>
                    <label class="form-label">Password
                        <input class="form-control" type="password" name="admin_password" minlength="12" maxlength="1024" autocomplete="new-password" required>
                        <span class="form-hint">Use at least 12 characters. Passwords are stored with Argon2id.</span>
                    </label>
                    <label class="form-label">Confirm password
                        <input class="form-control" type="password" name="admin_password_confirm" minlength="12" maxlength="1024" autocomplete="new-password" required>
                    </label>
                </section>

                <div class="setup-actions">
                    <div class="setup-checklist">
                        <span>✓ Database connection</span>
                        <span>✓ Migrations &amp; secure configuration</span>
                        <span>✓ Administrator account</span>
                    </div>
                    <button class="btn btn-primary btn-lg" type="submit" id="setup-submit">
                        <span class="setup-button-label">Set up my shop</span>
                        <span class="setup-button-loading" hidden>Setting up your shop…</span>
                        <span class="spinner-border spinner-border-sm" hidden aria-hidden="true"></span>
                    </button>
                </div>
                <p class="setup-footnote">Setup runs once over HTTPS. Existing accounts and unrelated database tables are never overwritten.</p>
            </form>
        <?php else: ?>
            <div class="setup-card setup-locked">
                <h2>Installation needs attention</h2>
                <p>To protect your database, browser setup is unavailable until the saved connection can be verified. Check the configuration from your hosting terminal, then reload this page.</p>
            </div>
        <?php endif; ?>
    </main>
    <?php if ($ready): ?>
        <script src="/assets/tabler/tabler.min.js" defer></script>
        <script src="/assets/js/setup.js" defer></script>
    <?php endif; ?>
</body>
</html>
