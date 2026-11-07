<?php
use App\Services\Auth;
use App\Services\Settings;
use App\Models\Database as DB;
use App\Middleware\Permissions as P;
$user = Auth::user();
$stored = DB::one('SELECT tabs FROM user_workspaces WHERE user_id=?',[$user['id']]);
$tabs = $stored?json_decode($stored['tabs'],true,32,JSON_THROW_ON_ERROR):[];
$brand = Settings::get('brand_name','Theatre Shop');
?>
<!doctype html><html lang="en" data-bs-theme="<?= e(Settings::get('theme_default','dark')) ?>">
<head><title><?= e($title ?? $brand) ?> | <?= e($brand) ?></title><?php require ROOT . '/app/Views/partials/head.php'; ?>
<script defer src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js" integrity="sha384-HZZ/fukV+9G8gwTNjN7zQDG0Sp7MsZy5DDN6VfY3Be7V9dvQpEpR2jF2HlyFUUjU" crossorigin="anonymous"></script>
</head><body data-user-id="<?= e($user['id']) ?>" data-can-inventory-edit="<?= P::allows('inventory.edit')?'1':'0' ?>">
<a href="#workspace-content" class="visually-hidden-focusable skip-link">Skip to workspace</a>
<div id="page-loader" class="page-loader" role="progressbar" aria-label="Loading workspace" hidden></div>
<header class="shop-header"><div class="header-main">
<a href="/dashboard" class="shop-brand" data-workspace><?php if (Settings::get('logo_light')!=='' || Settings::get('logo_dark')!==''): ?>
<?php foreach (['light','dark'] as $theme): ?><?php if (Settings::get('logo_' . $theme)!==''): ?><img class="<?= Settings::get('logo_' . ($theme==='light'?'dark':'light'))===''?'brand-shared':'brand-' . $theme ?>" src="/branding/logo_<?= $theme ?>" alt="<?= e($brand) ?>"><?php endif; ?><?php endforeach; ?>
<?php else: ?><span class="brand-mark material-symbols-rounded" aria-hidden="true">theater_comedy</span><span><?= e($brand) ?></span><?php endif; ?></a>
<div class="global-search"><span class="material-symbols-rounded" aria-hidden="true">search</span><input id="global-search" type="search" placeholder="Search shows, assets, orders..." aria-label="Search the shop" autocomplete="off"><div id="search-results" class="search-results" hidden></div></div>
<button class="btn header-icon" data-theme-toggle aria-label="Toggle color theme"><span class="material-symbols-rounded" aria-hidden="true">contrast</span></button>
<a href="/profile" data-workspace class="profile-link"><img src="/avatars/<?= e($user['id']) ?>" alt="My profile" class="avatar avatar-sm"><span class="profile-name"><?= e($user['name']) ?><small><?= e($user['role']) ?></small></span></a>
</div><nav class="primary-nav" aria-label="Shop navigation">
<?php foreach ([['/dashboard','Dashboard','space_dashboard','shows.view'],['/shows','Shows','theater_comedy','shows.view'],['/orders','Shop Orders','assignment','shows.view'],['/inventory','Inventory','inventory_2','inventory.view'],['/repairs','Repairs','build','reports.view'],['/reports/timeline','Timeline','view_timeline','reports.view'],['/reports','Reports','description','reports.view'],['/settings','Settings','settings','settings.edit']] as [$url,$label,$icon,$permission]): ?>
<?php if (P::allows($permission)): ?><a href="<?= e($url) ?>" data-workspace><span class="material-symbols-rounded" aria-hidden="true"><?= e($icon) ?></span><?= e($label) ?></a><?php endif; ?><?php endforeach; ?>
</nav><nav id="workspace-tabs" class="workspace-tabs" aria-label="Open workspaces" data-tabs="<?= e(json_encode($tabs,JSON_THROW_ON_ERROR)) ?>"></nav></header>
<main id="workspace-content" tabindex="-1" data-title="<?= e($title ?? 'Dashboard') ?>" data-label="<?= e($workspaceLabel ?? $title ?? 'Dashboard') ?>" data-key="<?= e($workspaceKey ?? 'dashboard') ?>"><?php echo $content; ?></main>
<div id="notifications" class="notifications" aria-live="polite"></div>
<dialog id="avatar-dialog" class="avatar-dialog"><form method="dialog"><button class="btn dialog-close" aria-label="Close avatar overlay"><span class="material-symbols-rounded" aria-hidden="true">close</span></button></form><h2>Your Thumbs avatar</h2><img id="avatar-preview" src="/avatars/<?= e($user['id']) ?>" alt="Your generated avatar"><p>A random seed makes this avatar uniquely yours. No photo uploads.</p><button class="btn btn-primary" data-regenerate-avatar><span class="material-symbols-rounded" aria-hidden="true">refresh</span>Rotate / regenerate</button></dialog>
</body></html>
