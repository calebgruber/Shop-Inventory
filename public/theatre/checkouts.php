<?php
$page  = 'checkouts';
$title = 'Checkouts';
require __DIR__ . '/_shared.php';

/* Fake checkout data */
$checkouts = [
  /* id,  co#,    item,                           sku,       person,       dept/show,            date_out,     due_date,     returned,    overdue */
  [1,  'CO-2847', 'Victorian Corset (Ivory, Sz 8)','CST-002', 'Maria T.',   'Great Expectations', '05/15/2025', '05/22/2025', false, false],
  [2,  'CO-2846', 'Top Hat (Black, Sz 7)',         'CST-010', 'Aaron B.',   'Hello, Dolly!',      '05/14/2025', '05/21/2025', false, false],
  [3,  'CO-2845', 'Period Pocket Watch (Silver)',  'PRO-001', 'Luke F.',    'Great Expectations', '05/12/2025', '05/19/2025', false, true],
  [4,  'CO-2844', 'Walking Stick (Oak)',           'PRO-020', 'Nora P.',    'Great Expectations', '05/12/2025', '05/19/2025', false, true],
  [5,  'CO-2843', 'Drill (Cordless, 18V)',         'TOL-003', 'Chris W.',   'Scene Shop',         '05/11/2025', '05/18/2025', false, true],
  [6,  'CO-2842', 'Flat Black Paint (Quart)',      'HW-002',  'Sam O.',     'Scene Shop',         '05/08/2025', '05/15/2025', false, true],
  [7,  'CO-2841', 'Tailcoat (Black, 42R)',         'CST-021', 'James H.',   'Hello, Dolly!',      '05/10/2025', '05/17/2025', false, true],
  [8,  'CO-2840', 'Oil-style Lantern',             'PRO-040', 'Tina M.',    'Great Expectations', '05/13/2025', '05/20/2025', false, false],
  [9,  'CO-2839', 'Hot Glue Gun',                  'TOL-004', 'Rachel S.',  'Costumes Dept',      '05/14/2025', '05/21/2025', false, false],
  [10, 'CO-2838', 'Petticoat (White, Med) ×2',    'CST-031', 'Sarah V.',   'Great Expectations', '05/13/2025', '05/20/2025', false, false],
  [11, 'CO-2837', 'Lace Trim (White, 3 yds)',      'FAB-004', 'Jenny R.',   'Costumes Dept',      '05/10/2025', '05/13/2025', true,  false],
  [12, 'CO-2836', 'Stage Hammer (16 oz)',          'TOL-001', 'Dave K.',    'Scene Shop',         '05/09/2025', '05/12/2025', true,  false],
  [13, 'CO-2835', 'Parasol (White Lace)',          'PRO-030', 'Emma N.',    'Hello, Dolly!',      '05/07/2025', '05/14/2025', true,  false],
  [14, 'CO-2834', 'Military Jacket (Red, Med)',    'CST-050', 'Carlos D.',  'The Fantasticks',    '05/05/2025', '05/12/2025', true,  false],
  [15, 'CO-2833', 'Velvet (Navy) — 5 yds',        'FAB-002', 'Anna K.',    'Costumes Dept',      '05/03/2025', '05/10/2025', true,  false],
];

$active   = array_filter($checkouts, fn($r) => !$r[10]);
$returned = array_filter($checkouts, fn($r) => $r[10]);
$overdue  = array_filter($checkouts, fn($r) => !$r[10] && $r[11]);
?>
        <!-- PAGE HEADER -->
        <div class="page-header d-print-none">
          <div class="container-xl">
            <div class="row g-2 align-items-center">
              <div class="col">
                <h1 class="page-title">Checkouts</h1>
                <div class="text-secondary mt-1">
                  <?= count($active) ?> active &nbsp;·&nbsp;
                  <span class="text-danger fw-semibold"><?= count($overdue) ?> overdue</span> &nbsp;·&nbsp;
                  <?= count($returned) ?> returned this week
                </div>
              </div>
              <div class="col-auto ms-auto">
                <div class="btn-list">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-checkout">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon"><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                    New Checkout
                  </button>
                  <a href="?export=csv" class="btn btn-outline-secondary">Export</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <main id="content" class="page-body">
          <div class="container-xl">

            <?php if (count($overdue) > 0): ?>
            <div class="alert alert-danger alert-dismissible mb-3" role="alert">
              <div class="d-flex">
                <div>
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon alert-icon"><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" /><path d="M12 16h.01" /></svg>
                </div>
                <div>
                  <h4 class="alert-title"><?= count($overdue) ?> item<?= count($overdue) !== 1 ? 's' : '' ?> overdue</h4>
                  <div class="text-secondary">These items are past their due return date. Please follow up with the borrower.</div>
                </div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <!-- ACTIVE CHECKOUTS -->
            <div class="card mb-4">
              <div class="card-header">
                <h3 class="card-title">
                  <span class="card-title-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /></svg>
                  </span>
                  <span>Active Checkouts</span>
                </h3>
                <div class="card-options">
                  <span class="badge bg-primary"><?= count($active) ?> out</span>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-vcenter table-hover card-table">
                    <thead>
                      <tr>
                        <th>CO #</th>
                        <th>Item</th>
                        <th>SKU</th>
                        <th>Checked Out To</th>
                        <th>Show / Dept</th>
                        <th>Date Out</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($active as [$id, $co, $item, $sku, $person, $show, $out_dt, $due_dt, $returned, $ov]): ?>
                      <tr class="<?= $ov ? 'table-danger' : '' ?>">
                        <td class="text-secondary small"><?= htmlspecialchars($co) ?></td>
                        <td><strong><?= htmlspecialchars($item) ?></strong></td>
                        <td class="text-secondary small"><?= htmlspecialchars($sku) ?></td>
                        <td>
                          <span class="avatar avatar-xs me-1" style="background-color: hsl(<?= (crc32($person) % 360 + 360) % 360 ?>,60%,60%)"><?= htmlspecialchars(substr($person, 0, 1)) ?></span>
                          <?= htmlspecialchars($person) ?>
                        </td>
                        <td><?= htmlspecialchars($show) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($out_dt) ?></td>
                        <td class="<?= $ov ? 'text-danger fw-bold' : '' ?>"><?= htmlspecialchars($due_dt) ?></td>
                        <td>
                          <?php if ($ov): ?>
                            <span class="badge bg-danger">Overdue</span>
                          <?php else: ?>
                            <span class="badge bg-info">Out</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="btn-list flex-nowrap">
                            <button class="btn btn-sm btn-success">Return</button>
                            <button class="btn btn-sm btn-outline-secondary">Extend</button>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- RECENT RETURNS -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">
                  <span class="card-title-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M9 11l-4 4l4 4m-4 -4h11a4 4 0 0 0 0 -8h-1" /></svg>
                  </span>
                  <span>Recent Returns</span>
                </h3>
                <div class="card-options">
                  <span class="badge bg-success"><?= count($returned) ?> returned</span>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-vcenter table-hover card-table">
                    <thead>
                      <tr>
                        <th>CO #</th>
                        <th>Item</th>
                        <th>SKU</th>
                        <th>Returned By</th>
                        <th>Show / Dept</th>
                        <th>Date Out</th>
                        <th>Due</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($returned as [$id, $co, $item, $sku, $person, $show, $out_dt, $due_dt, $ret, $ov]): ?>
                      <tr>
                        <td class="text-secondary small"><?= htmlspecialchars($co) ?></td>
                        <td><?= htmlspecialchars($item) ?></td>
                        <td class="text-secondary small"><?= htmlspecialchars($sku) ?></td>
                        <td>
                          <span class="avatar avatar-xs me-1" style="background-color: hsl(<?= (crc32($person) % 360 + 360) % 360 ?>,60%,60%)"><?= htmlspecialchars(substr($person, 0, 1)) ?></span>
                          <?= htmlspecialchars($person) ?>
                        </td>
                        <td><?= htmlspecialchars($show) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($out_dt) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($due_dt) ?></td>
                        <td><span class="badge bg-success">Returned</span></td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

          </div>
        </main>

        <!-- NEW CHECKOUT MODAL -->
        <div class="modal modal-blur fade" id="modal-checkout" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">New Checkout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label required">Item</label>
                  <select class="form-select">
                    <option value="">— Select item —</option>
                    <option>Victorian Corset (Ivory, Sz 8) — CST-002 [2 avail]</option>
                    <option>Top Hat (Black, Sz 7) — CST-010 [3 avail]</option>
                    <option>Stage Hammer (16 oz) — TOL-001 [3 avail]</option>
                    <option>Period Pocket Watch (Silver) — PRO-001 [3 avail]</option>
                    <option>Walking Stick (Oak) — PRO-020 [3 avail]</option>
                    <option>Drill (Cordless, 18V) — TOL-003 [1 avail]</option>
                  </select>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label required">Checked Out To</label>
                    <input type="text" class="form-control" placeholder="Name">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label required">Show / Department</label>
                    <select class="form-select">
                      <option>Great Expectations</option>
                      <option>Hello, Dolly!</option>
                      <option>The Fantasticks</option>
                      <option>Scene Shop</option>
                      <option>Costumes Dept</option>
                      <option>Lighting Dept</option>
                      <option>Sound Dept</option>
                    </select>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label required">Date Out</label>
                    <input type="date" class="form-control" value="<?= date('Y-m-d') ?>">
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label required">Due Date</label>
                    <input type="date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label">Notes</label>
                  <textarea class="form-control" rows="2" placeholder="Any special instructions…"></textarea>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Confirm Checkout</button>
              </div>
            </div>
          </div>
        </div>
<?php require __DIR__ . '/_shared_footer.php'; ?>
