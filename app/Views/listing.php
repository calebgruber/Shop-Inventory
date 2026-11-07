<?php use App\Middleware\Permissions as P; ?>
<div class="page-heading"><div><p class="eyebrow">THEATRE SHOP WORKSPACE</p><h1><?= e($title) ?></h1><p class="text-secondary"><?= $resource==='assets'?'Every physical item. Every show. Its complete story.':'Keep preparation, records, and shop activity in one place.' ?></p></div><div class="page-actions">
<?php if ($resource==='shows' && P::allows('shows.edit')): ?><a class="btn btn-primary" href="/shows/add" data-workspace><span class="material-symbols-rounded" aria-hidden="true">add</span>Add show</a><?php endif; ?>
<?php if (in_array($resource,['assets','packages','rules'],true)): ?><a class="btn" href="/inventory/categories" data-workspace>Categories</a><a class="btn" href="/inventory/packages" data-workspace>Packages</a><a class="btn" href="/inventory/rules" data-workspace>Rules</a>
<?php if (P::allows('inventory.edit')): ?><a class="btn btn-primary" href="<?= match($resource){'assets'=>'/inventory/import','packages'=>'/inventory/package/add',default=>'/inventory/rule/add'} ?>" data-workspace><?= $resource==='assets'?'Import assets':'Add ' . rtrim($resource,'s') ?></a><?php endif; ?><?php endif; ?>
<?php if ($resource==='users' && P::allows('users.edit')): ?><a href="/users/add" data-workspace class="btn btn-primary">Create user</a><?php endif; ?>
</div></div><?php require ROOT . '/app/Views/partials/table.php'; ?>
