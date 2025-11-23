<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.html");
    exit;
}

if (!isset($_GET['id'])) {
    die("Không tìm thấy đơn hàng.");
}

$uid = (int)$_SESSION['customer_id'];
$order_id = (int)$_GET['id'];

/* ===========================
   LẤY THÔNG TIN ĐƠN HÀNG
=========================== */
$stmt = $conn->prepare("
    SELECT order_id, total_amount, order_status, created_at 
    FROM orders 
    WHERE customer_id = ? AND order_id = ?
");
$stmt->bind_param("ii", $uid, $order_id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Đơn hàng không tồn tại hoặc bạn không có quyền xem.");
}

/* ===========================
   LẤY SẢN PHẨM TRONG ĐƠN
=========================== */
$stmt2 = $conn->prepare("
    SELECT oi.quantity, oi.price, p.product_name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$stmt2->bind_param("i", $order_id);
$stmt2->execute();
$items = $stmt2->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Chi tiết đơn hàng #<?= $order_id ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/style.css" rel="stylesheet">
<style>
    .order-box { background:#1f1f1f; padding:25px; border-radius:8px; }
    .product-img { width:60px; border-radius:5px; }
    .status-badge { padding:5px 10px; border-radius:5px; }
    .completed { background:#198754; color:white; }
    .pending { background:#ffc107; color:black; }
    .cancelled { background:#dc3545; color:white; }
</style>
</head>
<body>

<div class="container py-5">
    <div class="col-lg-8 mx-auto order-box text-light">

        <a href="history.php" class="btn btn-outline-light btn-sm mb-3">
            <i class="fa fa-arrow-left"></i> Quay lại
        </a>

        <h3 class="mb-3">Chi tiết đơn hàng #<?= $order_id ?></h3>

        <div class="mb-3">
            <strong>Ngày đặt:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?><br>
            <strong>Trạng thái:</strong>
            <span class="status-badge 
                <?= $order['order_status'] == 'completed' ? 'completed' : ($order['order_status']=='pending'?'pending':'cancelled') ?>">
                <?= ucfirst($order['order_status']) ?>
            </span><br>
            <strong>Tổng tiền:</strong> <?= number_format($order['total_amount'],0,',','.') ?>₫
        </div>

        <hr class="border-secondary">

        <h5>Sản phẩm trong đơn</h5>

        <table class="table table-dark align-middle mt-3">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th class="text-center">SL</th>
                    <th class="text-end">Giá</th>
                    <th class="text-end">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sum = 0;
                while ($it = $items->fetch_assoc()):
                    $line = $it['quantity'] * $it['price'];
                    $sum += $line;
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="../../uploads/<?= $it['image'] ?>" class="product-img me-2">
                            <?= htmlspecialchars($it['product_name']) ?>
                        </div>
                    </td>
                    <td class="text-center"><?= $it['quantity'] ?></td>
                    <td class="text-end"><?= number_format($it['price'],0,',','.') ?>₫</td>
                    <td class="text-end"><?= number_format($line,0,',','.') ?>₫</td>
                </tr>
                <?php endwhile; ?>
                <tr>
                    <td colspan="3" class="text-end"><strong>Tổng cộng</strong></td>
                    <td class="text-end"><strong><?= number_format($sum,0,',','.') ?>₫</strong></td>
                </tr>
            </tbody>
        </table>

    </div>
</div>

</body>
</html>
