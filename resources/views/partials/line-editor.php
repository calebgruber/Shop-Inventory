<div data-line-editor data-with-dates="<?= isset($show)?'1':'0' ?>">
<div data-line-rows></div><button class="btn" type="button" data-add-line><span class="material-symbols-rounded" aria-hidden="true">add</span>Add equipment line</button>
<template data-line-template><div class="equipment-line">
<label class="form-label">Equipment or package<select class="form-select" data-line-equipment required><option value="">Choose equipment...</option>
<?php foreach ($types as $type): ?><option value="type:<?= e($type['id']) ?>"><?= e($type['name']) ?></option><?php endforeach; ?>
<?php foreach ($packages ?? [] as $package): ?><?php $components = App\Models\Database::all('SELECT c.item_type_id,i.name FROM package_components c JOIN item_types i ON i.id=c.item_type_id WHERE c.package_id=?',[$package['id']]); ?><option value="package:<?= e($package['id']) ?>" data-components="<?= e(json_encode($components,JSON_THROW_ON_ERROR)) ?>">Package: <?= e($package['name']) ?></option><?php endforeach; ?></select></label>
<label class="form-label">Quantity<input class="form-control" data-line-quantity type="number" min="1" max="1000000" value="1" required></label>
<?php if (isset($show)): ?><label class="form-label">Prep start<input class="form-control" data-line-start type="date" value="<?= e($show['prep_start']) ?>" required></label><label class="form-label">Item / package return<input class="form-control" data-line-return type="date" value="<?= e($show['return_date']) ?>" required></label><?php endif; ?>
<button type="button" class="btn btn-outline-danger remove-line" data-remove-line aria-label="Remove equipment line"><span class="material-symbols-rounded" aria-hidden="true">close</span></button><div class="component-return-overrides" data-component-overrides></div></div></template></div>
