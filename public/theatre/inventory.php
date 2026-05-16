<?php
$page  = 'inventory';
$title = 'Inventory';
require __DIR__ . '/_shared.php';

/* Fake inventory data */
$items = [
  /* id, sku,       name,                         category,    location,     qty_total, qty_out, unit,    condition, notes */
  [1, 'CST-001', 'Victorian Corset (Ivory, Sz 6)',  'Costumes',  'Row A-1',   2, 0, 'each',  'Good',      'Dry clean only'],
  [2, 'CST-002', 'Victorian Corset (Ivory, Sz 8)',  'Costumes',  'Row A-1',   3, 1, 'each',  'Good',      ''],
  [3, 'CST-003', 'Victorian Corset (Ivory, Sz 10)', 'Costumes',  'Row A-1',   2, 0, 'each',  'Fair',      'Repair needed: boning'],
  [4, 'CST-010', 'Top Hat (Black, Sz 7)',           'Costumes',  'Row A-3',   4, 1, 'each',  'Good',      ''],
  [5, 'CST-011', 'Top Hat (Black, Sz 7-3/8)',       'Costumes',  'Row A-3',   3, 0, 'each',  'Excellent', ''],
  [6, 'CST-012', 'Top Hat (Black, Sz 7-5/8)',       'Costumes',  'Row A-3',   2, 0, 'each',  'Good',      ''],
  [7, 'CST-020', 'Tailcoat (Black, 40R)',           'Costumes',  'Row B-1',   2, 0, 'each',  'Good',      ''],
  [8, 'CST-021', 'Tailcoat (Black, 42R)',           'Costumes',  'Row B-1',   1, 1, 'each',  'Fair',      'Missing 1 button'],
  [9, 'CST-030', 'Petticoat (White, Sm)',           'Costumes',  'Row B-2',   5, 0, 'each',  'Good',      ''],
  [10,'CST-031', 'Petticoat (White, Med)',          'Costumes',  'Row B-2',   4, 2, 'each',  'Good',      ''],
  [11,'CST-040', 'Period Bonnet (Straw)',           'Costumes',  'Row B-3',   3, 0, 'each',  'Good',      ''],
  [12,'CST-050', 'Military Jacket (Red, Med)',      'Costumes',  'Row C-1',   2, 0, 'each',  'Excellent', ''],
  [13,'PRO-001', 'Period Pocket Watch (Silver)',    'Props',     'Cabinet 1', 4, 1, 'each',  'Good',      'Wind weekly'],
  [14,'PRO-002', 'Pocket Watch (Gold)',             'Props',     'Cabinet 1', 3, 0, 'each',  'Good',      ''],
  [15,'PRO-003', 'Candlestick (Brass)',             'Props',     'Cabinet 2', 8, 0, 'each',  'Good',      ''],
  [16,'PRO-010', 'Teapot (Ceramic, White)',         'Props',     'Cabinet 2', 3, 0, 'each',  'Good',      ''],
  [17,'PRO-011', 'Teapot (Cast Iron)',              'Props',     'Cabinet 2', 2, 0, 'each',  'Fair',      'Chipped lid'],
  [18,'PRO-020', 'Walking Stick (Oak)',             'Props',     'Cabinet 3', 5, 2, 'each',  'Good',      ''],
  [19,'PRO-030', 'Parasol (White Lace)',            'Props',     'Cabinet 3', 4, 0, 'each',  'Good',      ''],
  [20,'PRO-040', 'Lantern (Oil-style)',             'Props',     'Cabinet 3', 6, 1, 'each',  'Good',      'Battery powered'],
  [21,'PRO-050', 'Feather Fan (Ivory)',             'Props',     'Cabinet 4', 7, 0, 'each',  'Good',      ''],
  [22,'PRO-060', 'Goblet (Pewter)',                 'Props',     'Cabinet 4',12, 0, 'each',  'Good',      ''],
  [23,'FAB-001', 'Velvet (Burgundy)',               'Fabrics',   'Shelf F-1', 0, 0, 'yards', 'Good',      ''],
  [24,'FAB-002', 'Velvet (Navy)',                   'Fabrics',   'Shelf F-1',12, 0, 'yards', 'Good',      ''],
  [25,'FAB-003', 'Taffeta (Black)',                 'Fabrics',   'Shelf F-2',18, 0, 'yards', 'Good',      ''],
  [26,'FAB-004', 'Lace Trim (White)',               'Fabrics',   'Shelf F-3', 6, 3, 'yards', 'Good',      ''],
  [27,'FAB-005', 'Hook & Eye Tape',                 'Fabrics',   'Shelf F-3', 3, 0, 'yards', 'Good',      '⚠ Low stock'],
  [28,'FAB-006', 'Invisible Zipper 22 in',          'Fabrics',   'Bin F-1',   2, 0, 'each',  'Good',      '⚠ Low stock'],
  [29,'FAB-007', 'Crinoline (White)',               'Fabrics',   'Shelf F-2', 8, 0, 'yards', 'Good',      ''],
  [30,'FAB-008', 'Cotton Muslin (Natural)',         'Fabrics',   'Shelf F-4',30, 0, 'yards', 'Good',      ''],
  [31,'TOL-001', 'Stage Hammer (16 oz)',            'Tools',     'Toolbox A', 3, 0, 'each',  'Good',      ''],
  [32,'TOL-002', 'Circular Saw (7-1/4 in)',         'Tools',     'Cage',      1, 0, 'each',  'Good',      'Safety sign-out req.'],
  [33,'TOL-003', 'Drill (Cordless, 18V)',           'Tools',     'Cage',      2, 1, 'each',  'Good',      ''],
  [34,'TOL-004', 'Hot Glue Gun',                    'Tools',     'Toolbox B', 4, 1, 'each',  'Good',      ''],
  [35,'TOL-005', 'Sewing Machine (Brother)',        'Tools',     'Sewing Rm', 3, 0, 'each',  'Good',      ''],
  [36,'TOL-006', 'Serger (Singer)',                 'Tools',     'Sewing Rm', 1, 0, 'each',  'Good',      ''],
  [37,'TOL-007', 'Steam Iron',                      'Tools',     'Sewing Rm', 2, 0, 'each',  'Good',      ''],
  [38,'HW-001',  'Wood Screw #8 (Box)',             'Hardware',  'Bin H-1',   8, 0, 'boxes', 'Good',      ''],
  [39,'HW-002',  'Flat Black Paint (Quart)',        'Hardware',  'Paint Rm',  1, 1, 'each',  'Good',      '⚠ Low stock'],
  [40,'HW-003',  'Gaffer Tape (2 in Black)',        'Hardware',  'Bin H-2',   1, 0, 'rolls', 'Good',      '⚠ Critical stock'],
  [41,'HW-004',  'Gaffer Tape (2 in White)',        'Hardware',  'Bin H-2',   3, 0, 'rolls', 'Good',      ''],
  [42,'HW-005',  'Bobby Pins (Pack)',               'Hardware',  'Bin H-3',   2, 0, 'packs', 'Good',      '⚠ Low stock'],
];

$categories = ['All', 'Costumes', 'Props', 'Fabrics', 'Tools', 'Hardware'];
$filter_cat = $_GET['filter'] ?? 'all';
$search     = $_GET['q'] ?? '';
?>
        <!-- PAGE HEADER -->
        <div class="page-header d-print-none">
          <div class="container-xl">
            <div class="row g-2 align-items-center">
              <div class="col">
                <h1 class="page-title">Inventory</h1>
                <div class="text-secondary mt-1"><?= count($items) ?> items in system</div>
              </div>
              <div class="col-auto ms-auto">
                <div class="btn-list">
                  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-add-item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon"><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                    Add Item
                  </button>
                  <a href="?export=csv" class="btn btn-outline-secondary">Export CSV</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <main id="content" class="page-body">
          <div class="container-xl">

            <!-- FILTER BAR -->
            <div class="card mb-3">
              <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                  <div class="col-12 col-md-5">
                    <div class="input-group">
                      <span class="input-group-text">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="icon"><path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" /><path d="M21 21l-6 -6" /></svg>
                      </span>
                      <input type="text" class="form-control" placeholder="Search items…" value="<?= htmlspecialchars($search) ?>">
                    </div>
                  </div>
                  <div class="col-12 col-md-4">
                    <select class="form-select">
                      <?php foreach ($categories as $cat): ?>
                      <option <?= ($filter_cat === strtolower($cat)) ? 'selected' : '' ?>><?= $cat ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-md-3">
                    <select class="form-select">
                      <option selected>All Conditions</option>
                      <option>Excellent</option>
                      <option>Good</option>
                      <option>Fair</option>
                      <option>Poor</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- INVENTORY TABLE -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">
                  <span class="card-title-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /></svg>
                  </span>
                  <span>All Items</span>
                </h3>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-vcenter table-hover card-table">
                    <thead>
                      <tr>
                        <th style="width:100px">SKU</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Out</th>
                        <th class="text-center">Avail</th>
                        <th>Condition</th>
                        <th>Notes</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($items as [$id, $sku, $name, $cat, $loc, $total, $out, $unit, $cond, $notes]): ?>
                      <?php
                        $avail = $total - $out;
                        if ($avail === 0 && $out > 0) {
                          $status_class = 'bg-danger'; $status_text = 'All Out';
                        } elseif ($avail <= 1 || strpos($notes, '⚠') !== false) {
                          $status_class = 'bg-warning'; $status_text = 'Low';
                        } else {
                          $status_class = 'bg-success'; $status_text = 'In Stock';
                        }
                        $cond_class = match($cond) {
                          'Excellent' => 'text-success', 'Good' => 'text-primary',
                          'Fair' => 'text-warning', 'Poor' => 'text-danger', default => ''
                        };
                      ?>
                      <tr>
                        <td class="text-secondary small"><?= htmlspecialchars($sku) ?></td>
                        <td>
                          <strong><?= htmlspecialchars($name) ?></strong>
                          <span class="badge <?= $status_class ?> ms-1"><?= $status_text ?></span>
                        </td>
                        <td><?= htmlspecialchars($cat) ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($loc) ?></td>
                        <td class="text-center"><?= $total ?></td>
                        <td class="text-center"><?= $out > 0 ? '<span class="text-danger">'.$out.'</span>' : '—' ?></td>
                        <td class="text-center fw-bold"><?= $avail ?></td>
                        <td class="<?= $cond_class ?>"><?= htmlspecialchars($cond) ?></td>
                        <td class="text-secondary small"><?= htmlspecialchars(str_replace('⚠ ', '', $notes)) ?></td>
                        <td>
                          <div class="btn-list flex-nowrap">
                            <a href="checkouts.php?item=<?= $id ?>" class="btn btn-sm btn-primary">Check Out</a>
                            <a href="#" class="btn btn-sm btn-outline-secondary">Edit</a>
                          </div>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="card-footer d-flex align-items-center">
                <p class="m-0 text-secondary">Showing <strong><?= count($items) ?></strong> items</p>
                <ul class="pagination m-0 ms-auto">
                  <li class="page-item disabled"><a class="page-link" href="#">prev</a></li>
                  <li class="page-item active"><a class="page-link" href="#">1</a></li>
                  <li class="page-item"><a class="page-link" href="#">2</a></li>
                  <li class="page-item"><a class="page-link" href="#">next</a></li>
                </ul>
              </div>
            </div>

          </div>
        </main>

        <!-- ADD ITEM MODAL -->
        <div class="modal modal-blur fade" id="modal-add-item" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Add Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label required">Item Name</label>
                    <input type="text" class="form-control" placeholder="e.g. Victorian Corset (Ivory, Sz 8)">
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label required">SKU</label>
                    <input type="text" class="form-control" placeholder="e.g. CST-001">
                  </div>
                  <div class="col-md-3 mb-3">
                    <label class="form-label required">Category</label>
                    <select class="form-select">
                      <option>Costumes</option>
                      <option>Props</option>
                      <option>Fabrics</option>
                      <option>Tools</option>
                      <option>Hardware</option>
                    </select>
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label">Location</label>
                    <input type="text" class="form-control" placeholder="e.g. Row A-1">
                  </div>
                  <div class="col-md-2 mb-3">
                    <label class="form-label required">Qty</label>
                    <input type="number" class="form-control" min="0" value="1">
                  </div>
                  <div class="col-md-2 mb-3">
                    <label class="form-label">Unit</label>
                    <select class="form-select">
                      <option>each</option>
                      <option>yards</option>
                      <option>boxes</option>
                      <option>rolls</option>
                      <option>packs</option>
                    </select>
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label">Condition</label>
                    <select class="form-select">
                      <option>Excellent</option>
                      <option selected>Good</option>
                      <option>Fair</option>
                      <option>Poor</option>
                    </select>
                  </div>
                  <div class="col-12 mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" rows="2" placeholder="Any care instructions or special notes…"></textarea>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary me-auto" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Save Item</button>
              </div>
            </div>
          </div>
        </div>
<?php require __DIR__ . '/_shared_footer.php'; ?>
