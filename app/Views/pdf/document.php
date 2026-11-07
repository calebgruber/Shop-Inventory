<!doctype html><html lang="en"><head><meta charset="utf-8"><style>
@page { margin: 54px 36px 60px; }
body { font-family: DejaVu Sans, sans-serif; color: #172334; font-size: 9px; }
header { border-bottom: 3px solid #375dba; padding-bottom: 12px; }
header img { max-width: 160px; max-height: 48px; }
h1 { font-size: 21px; } h2 { font-size: 13px; margin-top: 24px; padding: 8px; background: #eef1f6; border-left: 4px solid #375dba; }
table { border-collapse: collapse; width: 100%; table-layout: fixed; }
th,td { border-bottom: 1px solid #dce1e8; padding: 6px; text-align: left; overflow-wrap: anywhere; }
th { background: #f2f4f8; } tr { page-break-inside: avoid; }
footer { position: fixed; bottom: -36px; font-size: 8px; color: #617085; }
.meta { color: #617085; } .empty { color: #617085; }
.attachment { page-break-before: always; } .attachment img { max-width: 100%; max-height: 600px; } pre { white-space: pre-wrap; overflow-wrap: anywhere; }
</style></head><body><header><?php if ($logoPath && is_file($logoPath)): ?><img src="<?= e($logoPath) ?>" alt="<?= e($brand) ?>"><?php else: ?><strong><?= e($brand) ?></strong><?php endif; ?><h1><?= e($title) ?></h1><p class="meta">Generated <?= e($generated) ?> by <?= e($user['name']) ?> | Record <?= e($id) ?></p></header>
<?php foreach ($sections as $section): ?><h2><?= e($section['title']) ?></h2>
<?php if (!$section['rows']): ?><p class="empty">No records.</p>
<?php elseif (count($section['rows'])===1 && count($section['rows'][0])>6): ?><table><tbody><?php foreach ($section['rows'][0] as $key=>$value): ?><tr><th><?= e(ucwords(str_replace('_',' ',$key))) ?></th><td><?= e($value) ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?><table><thead><tr><?php foreach (array_keys($section['rows'][0]) as $key): ?><th><?= e(str_replace('_',' ',$key)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($section['rows'] as $row): ?><tr><?php foreach ($row as $value): ?><td><?= e($value) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php endforeach; ?>
<?php foreach ($attachments as $attachment): ?><?php $path = ROOT . '/storage/uploads/' . basename($attachment['stored_name']); if (!is_file($path)) { throw new RuntimeException('An archive attachment is missing.'); } ?><section class="attachment"><h2>Show attachment: <?= e($attachment['original_name']) ?></h2><?php if (str_starts_with($attachment['mime'],'image/')): ?><img src="<?= e($path) ?>" alt="<?= e($attachment['original_name']) ?>"><?php else: ?><pre><?= e(file_get_contents($path)) ?></pre><?php endif; ?></section><?php endforeach; ?>
<footer><?= e($brand) ?> | <?= e($title) ?> | <?= e($generated) ?> | <?= e($user['name']) ?></footer></body></html>
