<?php
/**
 * Theatre Shop Inventory — shared header/navbar/footer partial.
 *
 * Usage:
 *   $page   = 'dashboard' | 'inventory' | 'checkouts'
 *   $title  = page title string
 * Then: require __DIR__ . '/_shared.php';
 *
 * Close the page at the bottom with require __DIR__ . '/_shared_footer.php';
 */
if (!isset($page))  $page  = 'dashboard';
if (!isset($title)) $title = 'Theatre Shop';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title><?= htmlspecialchars($title) ?> — Theatre Shop Inventory</title>
    <!-- PAGE LEVEL STYLES -->
    <link href="../dist/libs/nouislider/dist/nouislider.min.css?1778865600" rel="stylesheet" />
    <link href="../dist/libs/tom-select/dist/css/tom-select.bootstrap5.min.css?1778865600" rel="stylesheet" />
    <!-- GLOBAL MANDATORY STYLES -->
    <link href="../dist/css/tabler.css?1778865600" rel="stylesheet" />
    <!-- PLUGINS STYLES -->
    <link href="../dist/css/tabler-flags.css?1778865600" rel="stylesheet" />
    <link href="../dist/css/tabler-themes.css?1778865600" rel="stylesheet" />
    <!-- DEMO STYLES -->
    <link href="../preview/css/demo.css?1778865600" rel="stylesheet" />
    <!-- CUSTOM FONT -->
    <style>@import url("https://rsms.me/inter/inter.css");</style>
    <!-- SI CUSTOM OVERRIDES (matches public/index.php patterns) -->
    <style>
      /* ===== PAGE LOADER ===== */
      #si-page-loader {
        position: fixed; inset: 0; z-index: 99999;
        display: flex; align-items: center; justify-content: center;
        background: var(--tblr-bg-surface, #fff);
        transition: opacity 0.35s ease;
      }
      #si-page-loader.si-loader-hidden { opacity: 0; pointer-events: none; }
      .si-loader-ring {
        width: 3rem; height: 3rem;
        border: 3px solid var(--tblr-border-color, #dee2e6);
        border-top-color: var(--tblr-primary, #066fd1);
        border-radius: 50%;
        animation: si-spin 0.7s linear infinite;
      }
      @keyframes si-spin { to { transform: rotate(360deg); } }

      /* ===== FADE-IN ===== */
      @keyframes si-fadein {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
      }
      .si-animate { opacity: 0; animation: si-fadein 0.45s ease forwards; }

      /* ===== BORDER RADIUS ===== */
      .btn, .btn-close { border-radius: 1px !important; }
      .card, .alert, .badge, .avatar, .tag { border-radius: 2px !important; }
      .form-control, .form-select, .form-check-input, .input-group-text { border-radius: 1px !important; }

      /* ===== PAGE BACKGROUND ===== */
      html:not([data-bs-theme="dark"]) body,
      html:not([data-bs-theme="dark"]) .page { background-color: #f0f6ff; --si-page-bg: #f0f6ff; }
      html[data-bs-theme="dark"] body,
      html[data-bs-theme="dark"] .page { --si-page-bg: #111827; }

      /* ===== CARDS — left accent border + title chip ===== */
      .card {
        --si-card-accent: #2563eb;
        --si-card-text: #fff;
        border-left: 5px solid var(--si-card-accent) !important;
      }
      .card .card-title {
        display: inline-flex; align-items: center; gap: 0.5rem;
        width: fit-content; margin-bottom: 0 !important;
        padding: 0.25rem 0.9rem 0.25rem 0.5rem;
        border-radius: 0 9999px 9999px 0; line-height: 1.25;
        transition: background-color 0.2s ease, color 0.2s ease;
      }
      .card-title-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.75rem; height: 1.75rem; border-radius: 3px;
        margin-inline-end: 0; flex-shrink: 0; color: inherit;
      }
      html:not([data-bs-theme="dark"]) .card .card-title {
        background-color: var(--si-card-accent); color: var(--si-card-text);
      }
      html[data-bs-theme="dark"] .card .card-title {
        background-color: color-mix(in srgb, var(--si-card-accent) 15%, transparent);
        color: var(--si-card-accent);
      }

      /* ===== BADGES — light mode: white/black text for contrast ===== */
      html:not([data-bs-theme="dark"]) .badge.bg-primary,
      html:not([data-bs-theme="dark"]) .badge.bg-secondary,
      html:not([data-bs-theme="dark"]) .badge.bg-success,
      html:not([data-bs-theme="dark"]) .badge.bg-info,
      html:not([data-bs-theme="dark"]) .badge.bg-danger,
      html:not([data-bs-theme="dark"]) .badge.bg-dark,
      html:not([data-bs-theme="dark"]) .badge.bg-blue,
      html:not([data-bs-theme="dark"]) .badge.bg-azure,
      html:not([data-bs-theme="dark"]) .badge.bg-indigo,
      html:not([data-bs-theme="dark"]) .badge.bg-purple,
      html:not([data-bs-theme="dark"]) .badge.bg-pink,
      html:not([data-bs-theme="dark"]) .badge.bg-red,
      html:not([data-bs-theme="dark"]) .badge.bg-orange,
      html:not([data-bs-theme="dark"]) .badge.bg-teal,
      html:not([data-bs-theme="dark"]) .badge.bg-cyan,
      html:not([data-bs-theme="dark"]) .badge.bg-green   { color: #fff !important; }
      html:not([data-bs-theme="dark"]) .badge.bg-warning,
      html:not([data-bs-theme="dark"]) .badge.bg-yellow,
      html:not([data-bs-theme="dark"]) .badge.bg-lime    { color: #000 !important; }

      /* ===== DARK-MODE BADGES ===== */
      html[data-bs-theme="dark"] .badge.bg-primary   { --tblr-bg-opacity: 0.25; color: var(--tblr-primary)   !important; }
      html[data-bs-theme="dark"] .badge.bg-success   { --tblr-bg-opacity: 0.25; color: var(--tblr-success)   !important; }
      html[data-bs-theme="dark"] .badge.bg-warning   { --tblr-bg-opacity: 0.25; color: var(--tblr-warning)   !important; }
      html[data-bs-theme="dark"] .badge.bg-danger    { --tblr-bg-opacity: 0.25; color: var(--tblr-danger)    !important; }
      html[data-bs-theme="dark"] .badge.bg-info      { --tblr-bg-opacity: 0.25; color: var(--tblr-info)      !important; }
      html[data-bs-theme="dark"] .badge.bg-secondary { --tblr-bg-opacity: 0.25; color: var(--tblr-secondary) !important; }

      /* ===== NAVBAR / TAB HEADER ===== */
      .navbar-expand-md #navbar-menu .nav-item.active::after,
      .navbar-expand-md #navbar-menu .nav-item.show::after { display: none !important; }

      .page > .navbar {
        box-shadow: none !important;
        background: var(--tblr-bg-surface) !important;
        border-bottom: 0 !important;
        margin-bottom: 0 !important;
      }
      .si-site-header { padding-top: 8px !important; padding-bottom: 0 !important; }
      .si-site-header .container-xl {
        display: flex; flex-wrap: wrap; align-items: center; row-gap: 0.5rem;
      }
      .si-site-header .navbar-brand { order: 1; }
      .si-site-header .navbar-brand img {
        display: block; height: 36px; width: auto;
        max-width: 180px; object-fit: contain;
      }

      .si-live-datetime {
        display: inline-flex; align-items: center; gap: 0.5rem;
        font-variant-numeric: tabular-nums; font-size: 0.9rem;
        color: var(--tblr-secondary); padding: 0 0.35rem;
      }
      .si-live-time {
        display: inline-flex; align-items: baseline; gap: 0.05rem;
        color: var(--tblr-body-color); letter-spacing: 0.01em;
      }
      .si-live-hours-minutes { font-weight: 700; }
      .si-live-seconds { font-weight: 300; opacity: 0.7; }
      .si-live-date { font-size: 0.8rem; color: var(--tblr-secondary); white-space: nowrap; }

      .si-top-utility {
        order: 2; margin-left: auto;
        display: flex; align-items: center; gap: 0.5rem; flex-wrap: nowrap;
      }
      .si-top-utility > .d-none.d-md-flex { display: flex !important; margin-right: 0 !important; }

      .si-site-header .navbar-toggler { order: 3; }

      #navbar-menu {
        order: 4; flex-basis: 100%; width: 100%;
        padding-top: 0; margin-top: 0.25rem;
        border-top: 1px solid var(--tblr-border-color);
      }
      #navbar-menu .navbar-nav { gap: 0 !important; align-self: stretch; width: 100%; padding-top: 0; }
      #navbar-menu .navbar-nav .nav-item { margin: 0 !important; display: flex; justify-content: flex-end; }
      #navbar-menu .navbar-nav .nav-link {
        border: 0 !important;
        border-top: 4px solid transparent !important;
        border-bottom: 0 !important;
        border-radius: 0 !important;
        margin: 0 !important;
        padding-top: 0.6rem !important;
        padding-bottom: 0.6rem !important;
        background-color: transparent;
        transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
      }
      #navbar-menu .navbar-nav .nav-link:hover,
      #navbar-menu .navbar-nav .nav-link:focus {
        background-color: color-mix(in srgb, var(--tblr-primary) 10%, transparent) !important;
        border-top-color: var(--tblr-primary) !important;
      }
      #navbar-menu .navbar-nav .nav-link.active,
      #navbar-menu .navbar-nav .nav-item.active > .nav-link {
        background-color: var(--si-page-bg, var(--tblr-bg-surface)) !important;
        border-color: var(--tblr-primary) transparent transparent !important;
        color: var(--tblr-body-color) !important;
        box-shadow: none;
      }

      @media (max-width: 767.98px) {
        .si-live-datetime { display: none; }
        .si-site-header .navbar-toggler { order: 3; margin-left: auto; }
        .si-top-utility { order: 4; width: 100%; justify-content: flex-end; }
      }

      /* ===== PROGRESS BARS — static diagonal stripes ===== */
      .progress-bar {
        background-image: linear-gradient(
          45deg,
          rgba(255,255,255,.2) 25%, transparent 25%, transparent 50%,
          rgba(255,255,255,.2) 50%, rgba(255,255,255,.2) 75%,
          transparent 75%, transparent
        ) !important;
        background-size: 1rem 1rem !important;
        animation: none !important;
      }

      /* ===== NO-ACCENT CARDS (filter bars, utility panels) ===== */
      .card.si-no-accent {
        border-left: none !important;
      }

      /* ===== STAT CARDS ===== */
      .si-stat-value { font-size: 2rem; font-weight: 700; line-height: 1.1; }
      .si-stat-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: .05em; }
    </style>
  </head>
  <body>
    <!-- PAGE LOADER -->
    <div id="si-page-loader" aria-hidden="true"><div class="si-loader-ring"></div></div>
    <a href="#content" class="visually-hidden skip-link">Skip to main content</a>
    <script src="../dist/js/tabler-theme.min.js?1778865600"></script>
    <div class="page">

      <!-- NAVBAR -->
      <header class="navbar navbar-expand-md d-print-none si-site-header">
        <div class="container-xl">
          <!-- TOGGLER -->
          <button class="navbar-toggler" type="button"
            data-bs-toggle="collapse" data-bs-target="#navbar-menu"
            aria-controls="navbar-menu" aria-expanded="false"
            aria-label="Toggle primary navigation">
            <span class="navbar-toggler-icon"></span>
          </button>
          <!-- LOGO -->
          <div class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
            <a href="index.php" aria-label="Theatre Shop Inventory">
              <img src="https://cdn.calebgruber.me/CalebGruber..png" alt="Logo" height="36">
            </a>
          </div>
          <!-- UTILITY ROW -->
          <div class="navbar-nav flex-row si-top-utility">
            <div class="nav-item si-live-datetime" aria-live="polite">
              <span class="si-live-time">
                <span class="si-live-hours-minutes" id="si-live-hours-minutes">--:--</span><span class="si-live-seconds" id="si-live-seconds">:--</span>
              </span>
              <span class="si-live-date" id="si-live-date">--/--/----</span>
            </div>
            <div class="d-none d-md-flex me-3">
              <!-- THEME TOGGLE -->
              <div class="nav-item si-theme-toggle">
                <a href="?theme=dark" class="nav-link px-0 hide-theme-dark" title="Enable dark mode" data-bs-toggle="tooltip" data-bs-placement="bottom">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454l0 .008" /></svg>
                </a>
                <a href="?theme=light" class="nav-link px-0 hide-theme-light" title="Enable light mode" data-bs-toggle="tooltip" data-bs-placement="bottom">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0 -8 0" /><path d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7" /></svg>
                </a>
              </div>
            </div>
            <!-- USER MENU -->
            <div class="nav-item dropdown si-user-menu">
              <a href="#" class="nav-link d-flex lh-1 p-0 px-2" role="button"
                data-bs-toggle="dropdown" data-bs-auto-close="outside"
                aria-expanded="false" aria-label="Open user menu">
                <span class="avatar avatar-sm" style="background-image: url(../static/avatars/000m.jpg)"></span>
                <div class="d-none d-xl-block ps-2">
                  <div>Stage Manager</div>
                  <div class="mt-1 small text-secondary">Theatre Shop</div>
                </div>
              </a>
              <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                <div class="dropdown-header">
                  <strong>Stage Manager</strong><br>
                  <span class="text-secondary small">Theatre Shop</span>
                </div>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2 icon icon-sm"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855"/></svg>
                  Profile
                </a>
                <a class="dropdown-item" href="#">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2 icon icon-sm"><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0"/></svg>
                  Settings
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="login.php">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2 icon icon-sm"><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M9 12h12l-3 -3"/><path d="M18 15l3 -3"/></svg>
                  Logout
                </a>
              </div>
            </div>
          </div>
          <!-- NAV TABS -->
          <div class="collapse navbar-collapse" id="navbar-menu">
            <nav aria-label="Primary">
              <ul class="navbar-nav">
                <li class="nav-item<?= $page === 'dashboard'  ? ' active' : '' ?>">
                  <a class="nav-link<?= $page === 'dashboard'  ? ' active' : '' ?>" href="index.php"
                    <?= $page === 'dashboard' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>
                    </span>
                    <span class="nav-link-title">Dashboard</span>
                  </a>
                </li>
                <li class="nav-item<?= $page === 'inventory'  ? ' active' : '' ?>">
                  <a class="nav-link<?= $page === 'inventory'  ? ' active' : '' ?>" href="inventory.php"
                    <?= $page === 'inventory' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /><path d="M16 5.25l-8 4.5" /></svg>
                    </span>
                    <span class="nav-link-title">Inventory</span>
                  </a>
                </li>
                <li class="nav-item<?= $page === 'checkouts' ? ' active' : '' ?>">
                  <a class="nav-link<?= $page === 'checkouts' ? ' active' : '' ?>" href="checkouts.php"
                    <?= $page === 'checkouts' ? 'aria-current="page"' : '' ?>>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 12l.01 0" /><path d="M13 12l2 0" /><path d="M9 16l.01 0" /><path d="M13 16l2 0" /></svg>
                    </span>
                    <span class="nav-link-title">Checkouts</span>
                  </a>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </header>
      <!-- END NAVBAR -->

      <div class="page-wrapper">
