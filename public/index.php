<?php
$items = [
    ['name' => 'Laptop', 'sku' => 'INV-1001', 'quantity' => 12, 'status' => 'In Stock'],
    ['name' => 'Wireless Mouse', 'sku' => 'INV-1002', 'quantity' => 4, 'status' => 'Low Stock'],
    ['name' => 'Mechanical Keyboard', 'sku' => 'INV-1003', 'quantity' => 0, 'status' => 'Out of Stock'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop Inventory</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css">
</head>
<body>
<div class="page">
  <header class="navbar navbar-expand-md d-print-none">
    <div class="container-xl">
      <a class="navbar-brand navbar-brand-autodark" href="#">
        Shop Inventory
      </a>
    </div>
  </header>

  <div class="page-wrapper">
    <div class="page-header d-print-none">
      <div class="container-xl">
        <div class="row g-2 align-items-center">
          <div class="col">
            <h2 class="page-title">Inventory Dashboard</h2>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <div class="row row-deck row-cards">
          <div class="col-md-4">
            <div class="card">
              <div class="card-body">
                <h3 class="card-title">Total Products</h3>
                <div class="h1 mb-0"><?= count($items) ?></div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card">
              <div class="card-body">
                <h3 class="card-title">Low Stock</h3>
                <div class="h1 mb-0">
                  <?= count(array_filter($items, static fn($item) => $item['quantity'] > 0 && $item['quantity'] <= 5)) ?>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card">
              <div class="card-body">
                <h3 class="card-title">Out of Stock</h3>
                <div class="h1 mb-0">
                  <?= count(array_filter($items, static fn($item) => $item['quantity'] === 0)) ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card mt-4">
          <div class="card-header">
            <h3 class="card-title">Current Inventory</h3>
          </div>
          <div class="table-responsive">
            <table class="table card-table table-vcenter text-nowrap datatable">
              <thead>
              <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Quantity</th>
                <th>Status</th>
              </tr>
              </thead>
              <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($item['sku'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= (int) $item['quantity'] ?></td>
                  <td><?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
</body>
</html>
