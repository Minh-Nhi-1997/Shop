<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}

$uid = (int)$_SESSION['customer_id'];

$orders = [];
$stmt = $conn->prepare("SELECT order_id, total_amount, order_status, created_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Lịch sử đơn hàng - CakeZone</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="../../assets/img/favicon.ico" rel="icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .border-inner { border:1px solid rgba(255,255,255,0.06); }
        .status-badge.pending { background:#ffc107; color:#000; }
        .status-badge.completed { background:#198754; color:#fff; }
        .status-badge.cancelled { background:#dc3545; color:#fff; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container-fluid bg-primary py-5 mb-5 hero-header">
    <div class="container py-5">
        <div class="row justify-content-start">
            <div class="col-lg-8 text-center text-lg-start">
                <h1 class="font-secondary text-primary mb-4">My Orders</h1>
                <h1 class="display-1 text-uppercase text-white mb-4">Lịch Sử Đơn Hàng</h1>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="bg-dark border-inner p-4 rounded">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="text-white mb-0">Đơn hàng của tôi</h4>
                    <a href="index.php" class="btn btn-outline-primary btn-sm"><i class="fa fa-arrow-left"></i> Tiếp tục mua sắm</a>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="text-center text-secondary py-5">
                        <p class="mb-0">Bạn chưa có đơn hàng nào.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Ngày</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Tổng tiền</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): 
                                    $oid = (int)$order['order_id'];
                                    $status = strtolower($order['order_status']);
                                ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($oid) ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                                        <td>
                                            <span class="badge <?=
                                                $status === 'completed' ? 'status-badge completed' :
                                                ($status === 'pending' ? 'status-badge pending' : 'status-badge cancelled')
                                            ?>">
                                                <?= htmlspecialchars(ucfirst($order['order_status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?= number_format($order['total_amount'],0,',','.') ?>₫</td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#items-<?= $oid ?>" aria-expanded="false" aria-controls="items-<?= $oid ?>">
                                                Xem chi tiết
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="collapse-row">
                                        <td colspan="5" class="p-0">
                                            <div class="collapse" id="items-<?= $oid ?>">
                                                <div class="p-3 bg-secondary">
                                                    <table class="table table-sm table-dark mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Sản phẩm</th>
                                                                <th class="text-center">Số lượng</th>
                                                                <th class="text-end">Đơn giá</th>
                                                                <th class="text-end">Thành tiền</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $stmt2 = $conn->prepare("
                                                                SELECT oi.quantity, oi.price, p.product_name
                                                                FROM order_items oi
                                                                JOIN products p ON oi.product_id = p.product_id
                                                                WHERE oi.order_id = ?
                                                            ");
                                                            $stmt2->bind_param("i", $oid);
                                                            $stmt2->execute();
                                                            $res2 = $stmt2->get_result();
                                                            $sum = 0;
                                                            while ($it = $res2->fetch_assoc()):
                                                                $line = $it['price'] * $it['quantity'];
                                                                $sum += $line;
                                                            ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($it['product_name']) ?></td>
                                                                    <td class="text-center"><?= (int)$it['quantity'] ?></td>
                                                                    <td class="text-end"><?= number_format($it['price'],0,',','.') ?>₫</td>
                                                                    <td class="text-end"><?= number_format($line,0,',','.') ?>₫</td>
                                                                </tr>
                                                            <?php endwhile;
                                                            $stmt2->close();
                                                            ?>
                                                            <tr>
                                                                <td colspan="3" class="text-end"><strong>Tổng</strong></td>
                                                                <td class="text-end"><strong><?= number_format($sum,0,',','.') ?>₫</strong></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <div class="mt-3 text-end">
                                                        <a href="order-invoice.php?order_id=<?= $oid ?>" class="btn btn-sm btn-primary">In hóa đơn</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
<?php $conn->close(); ?>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>