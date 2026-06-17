<?php
/**
 * Theatre Shop Inventory — Login page template.
 *
 * Layout : full-viewport split — theatrical background left, login form right.
 * Theme  : light/dark mode via tabler-theme.min.js (same cookie as the rest of the app).
 *
 * To set a custom background image, change the CSS variable --si-login-bg-image below.
 * Leave it as "none" to use the default gradient.
 */
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Sign In — Theatre Shop Inventory</title>
    <!-- Tabler core styles -->
    <link href="../dist/css/tabler.css?1778865600" rel="stylesheet" />
    <link href="../dist/css/tabler-themes.css?1778865600" rel="stylesheet" />
    <!-- Custom font -->
    <style>@import url("https://rsms.me/inter/inter.css");</style>
    <style>
      /* ======================================================= */
      /* CUSTOM BACKGROUND — change the url() or swap gradient   */
      /* ======================================================= */
      :root {
        --si-login-bg-image: none;   /* e.g. url('/path/to/stage.jpg') */
        --si-login-bg-overlay: rgba(10, 4, 28, 0.72);
        --si-login-accent: #7c3aed;  /* violet theatre accent */
      }

      /* ===== BORDER RADIUS (matches rest of app) ===== */
      .btn, .btn-close { border-radius: 1px !important; }
      .card, .alert, .badge, .avatar, .tag { border-radius: 2px !important; }
      .form-control, .form-select, .form-check-input, .input-group-text { border-radius: 1px !important; }

      /* ===== FULL VIEWPORT LAYOUT ===== */
      html, body { height: 100%; margin: 0; }

      .si-login-wrap {
        display: flex;
        min-height: 100vh;
        min-height: 100dvh;
      }

      /* ===== LEFT: THEATRICAL BACKGROUND ===== */
      .si-login-bg {
        flex: 1 1 60%;
        position: relative;
        overflow: hidden;
        display: none; /* hidden on mobile */
        background-image: var(--si-login-bg-image, none);
        background-size: cover;
        background-position: center;
      }
      /* Default gradient when no image is set */
      .si-login-bg::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 30% 40%, #3b0764 0%, #1e0a4a 35%, #0a001e 70%, #050012 100%);
        z-index: 0;
      }
      /* When a custom bg image is set, this darkens it */
      .si-login-bg::after {
        content: '';
        position: absolute; inset: 0;
        background: var(--si-login-bg-overlay);
        z-index: 1;
      }
      /* Decorative curtain rings effect */
      .si-login-bg-deco {
        position: absolute; top: 0; left: 0; right: 0;
        height: 6px;
        background: repeating-linear-gradient(
          90deg,
          var(--si-login-accent) 0px, var(--si-login-accent) 18px,
          transparent 18px, transparent 32px
        );
        z-index: 2;
        opacity: 0.7;
      }
      .si-login-bg-content {
        position: relative; z-index: 2;
        display: flex; flex-direction: column;
        justify-content: center; align-items: flex-start;
        height: 100%; padding: 3rem 3.5rem;
        color: #fff;
      }
      .si-login-logo {
        display: block; height: 48px; width: auto;
        max-width: 200px; object-fit: contain;
        margin-bottom: 2.5rem;
        filter: brightness(0) invert(1);
      }
      .si-login-tagline-title {
        font-size: clamp(1.75rem, 3vw, 2.6rem);
        font-weight: 700; line-height: 1.2;
        margin-bottom: 1rem;
        text-shadow: 0 2px 12px rgba(0,0,0,0.5);
      }
      .si-login-tagline-sub {
        font-size: 1.05rem; color: rgba(255,255,255,0.72);
        max-width: 28rem; line-height: 1.6;
        text-shadow: 0 1px 6px rgba(0,0,0,0.4);
      }
      .si-login-badge-row {
        display: flex; gap: 0.75rem; margin-top: 2.5rem; flex-wrap: wrap;
      }
      .si-login-stat-chip {
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.22);
        border-radius: 4px;
        padding: 0.45rem 1rem;
        font-size: 0.85rem; color: rgba(255,255,255,0.9);
        backdrop-filter: blur(6px);
      }
      .si-login-stat-chip strong { display: block; font-size: 1.25rem; font-weight: 700; }
      .si-login-footer-note {
        position: absolute; bottom: 1.75rem; left: 3.5rem;
        font-size: 0.75rem; color: rgba(255,255,255,0.4);
        z-index: 2;
      }

      @media (min-width: 768px) {
        .si-login-bg { display: flex; }
      }

      /* ===== RIGHT: FORM PANEL ===== */
      .si-login-panel {
        flex: 0 0 100%;
        display: flex; flex-direction: column;
        background: var(--tblr-bg-surface, #fff);
        position: relative;
        overflow-y: auto;
      }
      @media (min-width: 768px) {
        .si-login-panel { flex: 0 0 40%; min-width: 380px; max-width: 520px; }
      }

      .si-login-panel-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.25rem 2rem;
        border-bottom: 1px solid var(--tblr-border-color, #dee2e6);
      }
      .si-login-panel-header .si-brand-text {
        font-size: 0.85rem; font-weight: 600;
        color: var(--tblr-body-color); white-space: nowrap;
      }

      .si-login-form-wrap {
        flex: 1 1 auto;
        display: flex; align-items: center; justify-content: center;
        padding: 2.5rem 2rem;
      }
      .si-login-form-inner { width: 100%; max-width: 360px; }

      .si-login-heading {
        font-size: 1.6rem; font-weight: 700;
        margin-bottom: 0.25rem;
        color: var(--tblr-body-color);
      }
      .si-login-sub {
        font-size: 0.9rem; color: var(--tblr-secondary);
        margin-bottom: 2rem;
      }

      .si-login-panel-footer {
        padding: 1rem 2rem;
        font-size: 0.78rem; color: var(--tblr-muted);
        text-align: center;
        border-top: 1px solid var(--tblr-border-color, #dee2e6);
      }

      /* Theme toggle button */
      .si-theme-btn {
        background: none; border: none; cursor: pointer;
        color: var(--tblr-secondary); padding: 0.25rem;
        display: flex; align-items: center;
        transition: color 0.15s ease;
      }
      .si-theme-btn:hover { color: var(--tblr-body-color); }

      /* Violet primary for the sign-in button */
      .btn-si-signin {
        background-color: var(--si-login-accent);
        border-color: var(--si-login-accent);
        color: #fff;
        transition: background-color 0.15s ease, border-color 0.15s ease;
      }
      .btn-si-signin:hover, .btn-si-signin:focus {
        background-color: #6d28d9; border-color: #6d28d9; color: #fff;
      }

      /* Page loader */
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
        border-top-color: var(--si-login-accent);
        border-radius: 50%;
        animation: si-spin 0.7s linear infinite;
      }
      @keyframes si-spin { to { transform: rotate(360deg); } }

      /* Fade-in */
      @keyframes si-fadein {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
      }
      .si-login-form-inner { animation: si-fadein 0.5s ease 0.15s both; }
    </style>
  </head>
  <body>
    <!-- Page loader -->
    <div id="si-page-loader" aria-hidden="true"><div class="si-loader-ring"></div></div>
    <!-- Tabler theme (reads cookie, sets data-bs-theme before paint) -->
    <script src="../dist/js/tabler-theme.min.js?1778865600"></script>

    <div class="si-login-wrap">

      <!-- ===================== LEFT: BACKGROUND ===================== -->
      <div class="si-login-bg" aria-hidden="true">
        <div class="si-login-bg-deco"></div>
        <div class="si-login-bg-content">
          <!-- Logo — swap src to your own logo; filter inverts it to white -->
          <img src="https://cdn.calebgruber.me/CalebGruber..png" alt="Theatre Shop" class="si-login-logo">
          <div class="si-login-tagline-title">
            Riverton Community<br>Theatre Shop
          </div>
          <p class="si-login-tagline-sub">
            Track costumes, props, fabric, and tools for every production — from opening night to closing curtain.
          </p>
          <div class="si-login-badge-row">
            <div class="si-login-stat-chip">
              <strong>247</strong>
              Inventory Items
            </div>
            <div class="si-login-stat-chip">
              <strong>18</strong>
              Active Checkouts
            </div>
            <div class="si-login-stat-chip">
              <strong>5</strong>
              Active Productions
            </div>
          </div>
        </div>
        <p class="si-login-footer-note">Riverton Community Theatre · Shop Inventory System</p>
      </div>

      <!-- ===================== RIGHT: FORM PANEL ===================== -->
      <div class="si-login-panel">

        <!-- Panel header — branding + theme toggle -->
        <div class="si-login-panel-header">
          <span class="si-brand-text">
            <!-- Theatre mask SVG icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1" style="vertical-align:-3px; color: var(--si-login-accent)" aria-hidden="true"><path d="M3 13c0 3.866 4.029 7 9 7s9 -3.134 9 -7"/><path d="M3 13c0 -3.866 4.029 -7 9 -7s9 3.134 9 7"/><path d="M9 10l.01 0"/><path d="M15 10l.01 0"/><path d="M9.5 15a3.5 3.5 0 0 0 5 0"/></svg>
            Theatre Shop Inventory
          </span>
          <!-- Theme toggle -->
          <div class="d-flex gap-1">
            <button class="si-theme-btn hide-theme-dark" title="Switch to dark mode"
              onclick="document.cookie='tablerTheme=dark;path=/;max-age=31536000'; location.reload();">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 0 7.92 12.446a9 9 0 1 1 -8.313 -12.454l0 .008"/></svg>
            </button>
            <button class="si-theme-btn hide-theme-light" title="Switch to light mode"
              onclick="document.cookie='tablerTheme=light;path=/;max-age=31536000'; location.reload();">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 12a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"/><path d="M3 12h1m8 -9v1m8 8h1m-9 8v1m-6.4 -15.4l.7 .7m12.1 -.7l-.7 .7m0 11.4l.7 .7m-12.1 -.7l-.7 .7"/></svg>
            </button>
          </div>
        </div>

        <!-- Login form -->
        <div class="si-login-form-wrap">
          <div class="si-login-form-inner">

            <h1 class="si-login-heading">Sign in</h1>
            <p class="si-login-sub">Enter your credentials to access the Theatre Shop.</p>

            <form method="post" action="index.php" novalidate>

              <div class="mb-3">
                <label class="form-label" for="login-username">Username or Email</label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855"/></svg>
                  </span>
                  <input type="text" id="login-username" name="username"
                    class="form-control" placeholder="jsmith or jsmith@theatre.org"
                    autocomplete="username" autofocus required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label" for="login-password">
                  Password
                  <a href="#" class="float-end small" tabindex="-1">Forgot password?</a>
                </label>
                <div class="input-group">
                  <span class="input-group-text">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z"/><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/></svg>
                  </span>
                  <input type="password" id="login-password" name="password"
                    class="form-control" placeholder="••••••••"
                    autocomplete="current-password" required>
                  <button type="button" class="btn btn-outline-secondary si-pw-toggle"
                    aria-label="Toggle password visibility" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/></svg>
                  </button>
                </div>
              </div>

              <div class="mb-4">
                <label class="form-check">
                  <input class="form-check-input" type="checkbox" name="remember">
                  <span class="form-check-label">Keep me signed in</span>
                </label>
              </div>

              <button type="submit" class="btn btn-si-signin w-100">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2" aria-hidden="true"><path d="M15 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M21 12h-13l3 -3"/><path d="M11 15l-3 -3"/></svg>
                Sign in
              </button>

            </form>

            <p class="mt-4 text-center" style="font-size:.83rem; color:var(--tblr-secondary)">
              Don't have an account?
              <a href="#">Request access</a>
            </p>

          </div>
        </div>

        <div class="si-login-panel-footer">
          &copy; <?= date('Y') ?> Riverton Community Theatre &mdash; Shop Inventory System
        </div>

      </div><!-- /.si-login-panel -->

    </div><!-- /.si-login-wrap -->

    <script src="../dist/js/tabler.min.js?1778865600"></script>
    <script>
      (function () {
        /* Page loader */
        var loader = document.getElementById('si-page-loader');
        function hideLoader() {
          if (!loader) return;
          loader.classList.add('si-loader-hidden');
          setTimeout(function () { loader.style.display = 'none'; }, 400);
        }
        if (document.readyState === 'complete') {
          setTimeout(hideLoader, 200);
        } else {
          window.addEventListener('load', function () { setTimeout(hideLoader, 200); });
        }

        /* Password show/hide toggle */
        var pwToggle = document.querySelector('.si-pw-toggle');
        var pwInput  = document.getElementById('login-password');
        if (pwToggle && pwInput) {
          pwToggle.addEventListener('click', function () {
            var show = pwInput.type === 'password';
            pwInput.type = show ? 'text' : 'password';
            pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
          });
        }
      })();
    </script>
  </body>
</html>
