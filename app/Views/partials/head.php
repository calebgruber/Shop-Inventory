<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(App\Middleware\Security::token()) ?>">
<link rel="stylesheet" href="/assets/tabler/tabler.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0">
<link rel="stylesheet" href="/assets/css/shop.css">
<?php if (App\Services\Settings::get('favicon')!==''): ?><link rel="icon" href="/branding/favicon"><?php endif; ?>
<script src="/assets/js/theme.js"></script>
<script defer src="/assets/tabler/tabler.min.js"></script>
<script defer src="/assets/js/shop.js"></script>
