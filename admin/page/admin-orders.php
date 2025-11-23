<?php
session_start();
require '../../user/page/connect-db.php';

// Auth
if (!isset($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}

// Handle status update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  $order_id = intval($_POST['order_id'] ?? 0);
  $status = trim($_POST['status'] ?? '');
  if ($order_id && in_array($status, ['pending', 'processing', 'shipped', 'completed'])) { // loại bỏ cancelled
    $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $status, $order_id);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: admin-orders.php");
  exit;
}

// Handle delete
if (isset($_GET['delete_id'])) {
  $delId = intval($_GET['delete_id']);
  $stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
  $stmt->bind_param("i", $delId);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
  $stmt->bind_param("i", $delId);
  $stmt->execute();
  $stmt->close();

  header('Location: admin-orders.php');
  exit;
}

// Filters & pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;
$q = trim($_GET['q'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

// build where
$where = "1";
$params = [];
$types = '';
if ($filterStatus !== '') {
  $where .= " AND o.order_status = ?";
  $params[] = $filterStatus;
  $types .= 's';
}
if ($q !== '') {
  if (ctype_digit($q)) {
    $where .= " AND o.order_id = ?";
    $params[] = intval($q);
    $types .= 'i';
  } else {
    $where .= " AND c.full_name LIKE ?";
    $params[] = "%$q%";
    $types .= 's';
  }
}

// total count
$sqlCount = "SELECT COUNT(*) FROM orders o JOIN customers c ON o.customer_id = c.customer_id WHERE $where";
$stmt = $conn->prepare($sqlCount);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($totalOrders);
$stmt->fetch();
$stmt->close();
$totalPages = max(1, ceil($totalOrders / $perPage));

// fetch orders
$sql = "SELECT o.order_id, o.customer_id, c.full_name, o.total_amount, o.order_status, o.created_at
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE $where
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($types) {
  $bindTypes = $types . 'ii';
  $bindParams = array_merge($params, [$perPage, $offset]);
  $stmt->bind_param($bindTypes, ...$bindParams);
} else {
  $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Quản lý đơn - CakeZone Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
  <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../assets/css/style.css" rel="stylesheet">
  <style>
    .sidebar { background: #2c3e50; min-height: 100vh; padding: 20px; color: #fff }
    .sidebar a { color: #fff; text-decoration: none; display: block; padding: 10px; border-radius: 6px; margin-bottom: 6px }
    .sidebar a.active { background: #fd7e14 }
    .main-content { padding: 25px }
    .table-fixed thead { position: sticky; top: 0; background: #fff; z-index: 1; }
    .status-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px }
    .status-pending { background:#ffc107 }
    .status-processing { background:#0d6efd }
    .status-shipped { background:#0dcaf0 }
    .status-completed { background:#198754 }
  </style>
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <?php include 'sidebar.php'; ?>
    <div class="col-md-9 main-content">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Quản lý đơn hàng</h3>
        <a href="admin-orders.php" class="btn btn-outline-secondary btn-sm">Làm mới</a>
      </div>

      <form method="get" class="row g-2 mb-3">
        <div class="col-md-3">
          <input type="text" name="q" value="<?= htmlspecialchars($q); ?>" class="form-control" placeholder="Tìm theo ID hoặc tên khách">
        </div>
        <div class="col-md-3">
          <select name="status" class="form-control">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
            <option value="processing" <?= $filterStatus==='processing'?'selected':'' ?>>Processing</option>
            <option value="shipped" <?= $filterStatus==='shipped'?'selected':'' ?>>Shipped</option>
            <option value="completed" <?= $filterStatus==='completed'?'selected':'' ?>>Completed</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary">Lọc</button>
        </div>
      </form>

      <div class="card mb-3">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Mã</th>
                  <th>Khách hàng</th>
                  <th>Số món</th>
                  <th class="text-end">Tổng tiền</th>
                  <th>Trạng thái</th>
                  <th>Ngày</th>
                  <th class="text-end"></th>
                </tr>
              </thead>
              <tbody>
                <?php if(empty($orders)): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">Không có đơn hàng.</td>
                  </tr>
                <?php else: foreach($orders as $o):
                  $stmt2 = $conn->prepare("SELECT SUM(quantity) FROM order_items WHERE order_id = ?");
                  $stmt2->bind_param("i",$o['order_id']);
                  $stmt2->execute();
                  $stmt2->bind_result($itemCount);
                  $stmt2->fetch();
                  $stmt2->close();
                  $itemCount = (int)$itemCount;
                  $status = $o['order_status'];
                ?>
                <tr>
                  <td>#<?= htmlspecialchars($o['order_id']); ?></td>
                  <td><?= htmlspecialchars($o['full_name']); ?></td>
                  <td><?= $itemCount; ?></td>
                  <td class="text-end"><?= number_format($o['total_amount'],0,',','.'); ?>₫</td>
                  <td>
                    <span class="status-dot status-<?= htmlspecialchars($status); ?>"></span>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="update_status">
                      <input type="hidden" name="order_id" value="<?= $o['order_id']; ?>">
                      <select name="status" class="form-select form-select-sm d-inline-block" style="width:140px; display:inline-block" onchange="this.form.submit()">
                        <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
                        <option value="processing" <?= $status==='processing'?'selected':'' ?>>Processing</option>
                        <option value="shipped" <?= $status==='shipped'?'selected':'' ?>>Shipped</option>
                        <option value="completed" <?= $status==='completed'?'selected':'' ?>>Completed</option>
                      </select>
                    </form>
                  </td>
                  <td><?= date('d/m/Y H:i', strtotime($o['created_at'])); ?></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#items-<?= $o['order_id']; ?>">Xem chi tiết</button>
                  </td>
                </tr>
                <tr class="collapse-row">
                  <td colspan="7" class="p-0">
                    <div class="collapse" id="items-<?= $o['order_id']; ?>">
                      <div class="p-3 bg-light">
                        <strong>Chi tiết đơn hàng #<?= $o['order_id']; ?></strong>
                        <div class="table-responsive mt-2">
                          <table class="table table-sm mb-0">
                            <thead>
                              <tr>
                                <th>Ảnh Sản phẩm</th>
                                <th>Tên Sản phẩm</th>
                                <th class="text-center">Số lượng</th>
                                <th class="text-end">Đơn giá</th>
                                <th class="text-end">Thành tiền</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php
                              $stmt3 = $conn->prepare("SELECT oi.quantity, oi.price, p.product_name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
                              $stmt3->bind_param("i",$o['order_id']);
                              $stmt3->execute();
                              $res3 = $stmt3->get_result();
                              $sum = 0;
                              while($it = $res3->fetch_assoc()):
                                $line = $it['price']*$it['quantity'];
                                $sum += $line;
                              ?>
                              <tr>
                                <td><img src="../../assets/img/<?= htmlspecialchars($it['image']); ?>" alt="<?= htmlspecialchars($it['product_name']); ?>" style="width:50px; height:50px; object-fit:cover;"></td>
                                <td><?= htmlspecialchars($it['product_name']); ?></td>
                                <td class="text-center"><?= (int)$it['quantity']; ?></td>
                                <td class="text-end"><?= number_format($it['price'],0,',','.'); ?>₫</td>
                                <td class="text-end"><?= number_format($line,0,',','.'); ?>₫</td>
                              </tr>
                              <?php endwhile; $stmt3->close(); ?>
                              <tr>
                                <td colspan="3" class="text-end"><strong>Tổng</strong></td>
                                <td class="text-end"><strong><?= number_format($sum,0,',','.'); ?>₫</strong></td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <?php if($totalPages>1): ?>
      <nav>
        <ul class="pagination justify-content-center">
          <?php if($page>1): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($filterStatus) ?>">« Trước</a></li>
          <?php endif; ?>
          <?php for($i=1;$i<=$totalPages;$i++): ?>
            <li class="page-item <?= ($i==$page)?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($filterStatus) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
          <?php if($page<$totalPages): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&q=<?= urlencode($q) ?>&status=<?= urlencode($filterStatus) ?>">Tiếp »</a></li>
          <?php endif; ?>
        </ul>
      </nav>
      <?php endif; ?>

    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
