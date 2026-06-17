<?php
$page  = 'dashboard';
$title = 'Dashboard';
require __DIR__ . '/_shared.php';
?>
        <!-- PAGE HEADER -->
        <div class="page-header d-print-none">
          <div class="container-xl">
            <div class="row g-2 align-items-center">
              <div class="col">
                <h1 class="page-title">Dashboard</h1>
                <div class="text-secondary mt-1">
                  Riverton Community Theatre — Season 2025–26
                </div>
              </div>
              <div class="col-auto ms-auto">
                <div class="btn-list">
                  <a href="inventory.php" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon"><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                    Add Item
                  </a>
                  <a href="checkouts.php" class="btn btn-outline-secondary">
                    New Checkout
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- END PAGE HEADER -->

        <main id="content" class="page-body">
          <div class="container-xl">

            <!-- STAT CARDS ROW -->
            <div class="row g-3 mb-4">
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                      <div class="me-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /></svg>
                      </div>
                      <div>
                        <div class="si-stat-value">324</div>
                        <div class="si-stat-label text-secondary">Total Items</div>
                      </div>
                    </div>
                    <div class="progress progress-sm">
                      <div class="progress-bar bg-primary" style="width:81%"></div>
                    </div>
                    <div class="small text-secondary mt-1">81% storage capacity used</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                      <div class="me-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-info"><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /></svg>
                      </div>
                      <div>
                        <div class="si-stat-value">47</div>
                        <div class="si-stat-label text-secondary">Checked Out</div>
                      </div>
                    </div>
                    <div class="progress progress-sm">
                      <div class="progress-bar bg-info" style="width:47%"></div>
                    </div>
                    <div class="small text-secondary mt-1">47 of 100 in circulation</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                      <div class="me-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-warning"><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" /><path d="M12 16h.01" /></svg>
                      </div>
                      <div>
                        <div class="si-stat-value">12</div>
                        <div class="si-stat-label text-secondary">Low Stock</div>
                      </div>
                    </div>
                    <div class="progress progress-sm">
                      <div class="progress-bar bg-warning" style="width:12%"></div>
                    </div>
                    <div class="small text-secondary mt-1">12 items below reorder threshold</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-xl-3">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                      <div class="me-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-danger"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 8l0 4" /><path d="M12 16l.01 0" /></svg>
                      </div>
                      <div>
                        <div class="si-stat-value">3</div>
                        <div class="si-stat-label text-secondary">Overdue</div>
                      </div>
                    </div>
                    <div class="progress progress-sm">
                      <div class="progress-bar bg-danger" style="width:6%"></div>
                    </div>
                    <div class="small text-secondary mt-1">3 items past due return date</div>
                  </div>
                </div>
              </div>
            </div>

            <div class="row g-3">
              <!-- RECENT ACTIVITY -->
              <div class="col-12 col-xl-8">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">
                      <span class="card-title-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" /><path d="M12 7l0 5l3 3" /></svg>
                      </span>
                      <span>Recent Activity</span>
                    </h3>
                    <div class="card-options">
                      <a href="checkouts.php" class="btn btn-sm btn-outline-secondary">View all</a>
                    </div>
                  </div>
                  <div class="card-body p-0">
                    <div class="table-responsive">
                      <table class="table table-vcenter card-table">
                        <thead>
                          <tr>
                            <th>Item</th>
                            <th>Person</th>
                            <th>Show / Dept</th>
                            <th>Action</th>
                            <th>Date</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr>
                            <td>Victorian Corset (Ivory, Sz 8)</td>
                            <td>Maria T.</td>
                            <td>Great Expectations</td>
                            <td><span class="badge bg-info">Checked Out</span></td>
                            <td class="text-secondary">05/15/2025</td>
                          </tr>
                          <tr>
                            <td>Stage Hammer (16 oz)</td>
                            <td>Dave K.</td>
                            <td>Scene Shop</td>
                            <td><span class="badge bg-success">Returned</span></td>
                            <td class="text-secondary">05/14/2025</td>
                          </tr>
                          <tr>
                            <td>Top Hat (Black, Sz 7⅜)</td>
                            <td>Aaron B.</td>
                            <td>Hello, Dolly!</td>
                            <td><span class="badge bg-info">Checked Out</span></td>
                            <td class="text-secondary">05/14/2025</td>
                          </tr>
                          <tr>
                            <td>Lace Trim (White, 3 yds)</td>
                            <td>Jenny R.</td>
                            <td>Costumes Dept</td>
                            <td><span class="badge bg-success">Returned</span></td>
                            <td class="text-secondary">05/13/2025</td>
                          </tr>
                          <tr>
                            <td>Flat Black Paint (Quart)</td>
                            <td>Sam O.</td>
                            <td>Scene Shop</td>
                            <td><span class="badge bg-danger">Overdue</span></td>
                            <td class="text-secondary">05/08/2025</td>
                          </tr>
                          <tr>
                            <td>Period Pocket Watch (Silver)</td>
                            <td>Luke F.</td>
                            <td>Great Expectations</td>
                            <td><span class="badge bg-info">Checked Out</span></td>
                            <td class="text-secondary">05/12/2025</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <!-- RIGHT COLUMN -->
              <div class="col-12 col-xl-4">
                <!-- LOW STOCK ALERTS -->
                <div class="card mb-3">
                  <div class="card-header">
                    <h3 class="card-title">
                      <span class="card-title-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0z" /><path d="M12 16h.01" /></svg>
                      </span>
                      <span>Low Stock Alerts</span>
                    </h3>
                  </div>
                  <div class="list-group list-group-flush">
                    <div class="list-group-item">
                      <div class="row align-items-center">
                        <div class="col text-truncate">
                          <strong>Gaffer Tape (2 in)</strong>
                          <div class="small text-secondary">1 roll remaining</div>
                        </div>
                        <div class="col-auto"><span class="badge bg-danger">Critical</span></div>
                      </div>
                    </div>
                    <div class="list-group-item">
                      <div class="row align-items-center">
                        <div class="col text-truncate">
                          <strong>Bobby Pins (Pack)</strong>
                          <div class="small text-secondary">2 packs remaining</div>
                        </div>
                        <div class="col-auto"><span class="badge bg-warning">Low</span></div>
                      </div>
                    </div>
                    <div class="list-group-item">
                      <div class="row align-items-center">
                        <div class="col text-truncate">
                          <strong>Flat Black Paint (Qt)</strong>
                          <div class="small text-secondary">1 quart remaining</div>
                        </div>
                        <div class="col-auto"><span class="badge bg-danger">Critical</span></div>
                      </div>
                    </div>
                    <div class="list-group-item">
                      <div class="row align-items-center">
                        <div class="col text-truncate">
                          <strong>Hook &amp; Eye Tape (yd)</strong>
                          <div class="small text-secondary">3 yards remaining</div>
                        </div>
                        <div class="col-auto"><span class="badge bg-warning">Low</span></div>
                      </div>
                    </div>
                    <div class="list-group-item">
                      <div class="row align-items-center">
                        <div class="col text-truncate">
                          <strong>Invisible Zipper 22 in</strong>
                          <div class="small text-secondary">2 remaining</div>
                        </div>
                        <div class="col-auto"><span class="badge bg-warning">Low</span></div>
                      </div>
                    </div>
                  </div>
                  <div class="card-footer">
                    <a href="inventory.php?filter=low_stock" class="btn btn-sm btn-outline-warning w-100">View all low-stock items</a>
                  </div>
                </div>

                <!-- INVENTORY BY CATEGORY -->
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">
                      <span class="card-title-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="icon icon-1"><path d="M3 3m0 2a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2z" /><path d="M3 9h18" /><path d="M3 15h18" /></svg>
                      </span>
                      <span>By Category</span>
                    </h3>
                  </div>
                  <div class="card-body">
                    <?php
                    $categories = [
                      ['Costumes',   142, '#7c3aed'],
                      ['Props',       68, '#0891b2'],
                      ['Fabrics',     55, '#16a34a'],
                      ['Tools',       39, '#c2410c'],
                      ['Hardware',    20, '#b45309'],
                    ];
                    $total = 324;
                    foreach ($categories as [$name, $qty, $color]):
                      $pct = round($qty / $total * 100);
                    ?>
                    <div class="mb-3">
                      <div class="d-flex justify-content-between mb-1">
                        <span><?= $name ?></span>
                        <span class="text-secondary"><?= $qty ?></span>
                      </div>
                      <div class="progress progress-sm">
                        <div class="progress-bar" style="width:<?= $pct ?>%; background-color: <?= $color ?>;"></div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </main>
<?php require __DIR__ . '/_shared_footer.php'; ?>
