<?php
session_start();
require './connect-db.php'; // file kết nối DB

// Thống kê
$statProducts = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'] ?? 0;
$statOrders   = $conn->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc()['total'] ?? 0;
$statUsers    = $conn->query("SELECT COUNT(*) AS total FROM customers")->fetch_assoc()['total'] ?? 0;
$statRevenue  = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE order_status='completed'")->fetch_assoc()['revenue'] ?? 0;

// Đơn hàng gần đây (limit 5)
$recentOrders = [];
$res = $conn->query("
    SELECT o.order_id, c.full_name, o.total_amount, o.order_status
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    ORDER BY o.created_at DESC
    LIMIT 5
");
while($row = $res->fetch_assoc()) $recentOrders[] = $row;

// Sản phẩm mới (limit 5)
$recentProducts = [];
$res = $conn->query("
    SELECT product_name, price, stock
    FROM products
    ORDER BY created_at DESC
    LIMIT 5
");
while($row = $res->fetch_assoc()) $recentProducts[] = $row;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Dashboard - CakeZone Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="../assests/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assests/css/style.css" rel="stylesheet">
    <style>
        .sidebar { background:#2c3e50; min-height:100vh; padding:20px; }
        .sidebar a { color:#fff; text-decoration:none; display:block; padding:10px 15px; border-radius:5px; margin-bottom:5px; }
        .sidebar a:hover, .sidebar a.active { background:#fd7e14; }
        .main-content { padding:30px; }
        .stat-card { border-radius:8px; padding:20px; color:#fff; }
        .stat-card .value { font-size:1.6rem; font-weight:700; }
        .stat-card .label { opacity:.9; }
        .table-responsive { max-height:320px; overflow:auto; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3 sidebar">
            <h5 class="text-white mb-4"><i class="fa fa-cogs"></i> Admin Panel</h5>
            <a href="admin-dashboard.php" class="nav-link active"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
            <a href="admin-products.php" class="nav-link"><i class="fa fa-birthday-cake"></i> Sản phẩm</a>
            <a href="admin-orders.php" class="nav-link"><i class="fa fa-shopping-cart"></i> Đơn hàng</a>
            <a href="admin-users.php" class="nav-link"><i class="fa fa-users"></i> Người dùng</a>
            <hr class="bg-white">
            <a href="logout.php" class="nav-link"><i class="fa fa-sign-out-alt"></i> Đăng xuất</a>
        </div>

        <!-- Main Content -->
        <div class="col-md-9 main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Dashboard</h2>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card bg-primary">
                        <div class="label">Tổng sản phẩm</div>
                        <div class="value"><?= $statProducts ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-success">
                        <div class="label">Đơn hàng</div>
                        <div class="value"><?= $statOrders ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning">
                        <div class="label">Người dùng</div>
                        <div class="value"><?= $statUsers ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-danger">
                        <div class="label">Doanh thu (tạm)</div>
                        <div class="value"><?= number_format($statRevenue) ?> VNĐ</div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders & Products -->
            <div class="row">
                <div class="col-lg-7 mb-4">
                    <div class="card border-inner">
                        <div class="card-header bg-white">
                            <strong>Đơn hàng gần đây</strong>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mã</th>
                                            <th>Khách hàng</th>
                                            <th>Tổng tiền</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentOrders as $o): ?>
                                        <tr>
                                            <td><?= $o['order_id'] ?></td>
                                            <td><?= htmlspecialchars($o['full_name']) ?></td>
                                            <td><?= number_format($o['total_amount']) ?> VNĐ</td>
                                            <td>
                                                <span class="badge <?= $o['order_status']=='completed'?'bg-success':($o['order_status']=='processing'?'bg-warning':'bg-secondary') ?>">
                                                    <?= ucfirst($o['order_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-4">
                    <div class="card border-inner">
                        <div class="card-header bg-white">
                            <strong>Sản phẩm mới</strong>
                        </div>
                        <div class="card-body">
                            <?php foreach($recentProducts as $p): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <div style="width:54px;height:42px;background:#eee;display:flex;align-items:center;justify-content:center;border-radius:6px;margin-right:10px;">
                                        <i class="fa fa-birthday-cake"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($p['product_name']) ?></div>
                                        <div class="small text-muted"><?= number_format($p['price']) ?> VNĐ • Tồn: <?= $p['stock'] ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-inner">
                <div class="card-body">
                    <h6>Nhanh</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="admin-products.php" class="btn btn-outline-primary btn-sm"><i class="fa fa-birthday-cake"></i> Quản lý sản phẩm</a>
                        <a href="admin-orders.php" class="btn btn-outline-success btn-sm"><i class="fa fa-shopping-cart"></i> Quản lý đơn</a>
                        <a href="admin-users.php" class="btn btn-outline-warning btn-sm"><i class="fa fa-users"></i> Quản lý người dùng</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
