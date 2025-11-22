<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.html");
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$msg = '';
$feedback_msg = '';
$order_id = null;
$order_items = [];
$total_amount = 0;

// Xử lý thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $stmt = $conn->prepare("
        SELECT ci.product_id, ci.quantity, p.price, p.product_name 
        FROM cart_items ci 
        JOIN products p ON ci.product_id = p.product_id 
        WHERE ci.customer_id = ?
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!empty($cart_items)) {
        foreach ($cart_items as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }

        // Thêm đơn hàng
        $stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, order_status, created_at) VALUES (?, ?, 'completed', NOW())");
        $stmt->bind_param("id", $uid, $total_amount);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // Thêm chi tiết đơn hàng
        foreach ($cart_items as $item) {
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt->execute();
            $stmt->close();
        }

        // Xóa giỏ hàng
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE customer_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $stmt->close();

        $msg = "Thanh toán thành công!";
    } else {
        $msg = "Giỏ hàng trống!";
    }
}

// Lấy chi tiết đơn hàng nếu đã thanh toán
if ($order_id) {
    $stmt = $conn->prepare("
        SELECT oi.quantity, oi.price, p.product_name 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.product_id 
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order_items = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $total_amount = 0;
    foreach ($order_items as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Thanh toán - CakeZone</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="../../assets/img/favicon.ico" rel="icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Oswald:wght@500;600;700&family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid bg-primary py-5 mb-5 hero-header">
        <div class="container py-5">
            <div class="row justify-content-start">
                <div class="col-lg-8 text-center text-lg-start">
                    <h1 class="font-secondary text-primary mb-4">Checkout</h1>
                    <h1 class="display-1 text-uppercase text-white mb-4">Thanh Toán</h1>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="bg-dark border-inner p-5 rounded">
                        
                        <?php if ($msg): ?>
                            <div class="alert alert-success alert-dismissible fade show border-inner" role="alert">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!$order_id): ?>
                            <!-- Form thanh toán -->
                            <h3 class="text-white text-uppercase mb-4">
                                <i class="fa fa-shopping-cart text-primary"></i> Xác Nhận Thanh Toán
                            </h3>

                            <div class="bg-secondary border-inner p-4 rounded mb-4">
                                <h5 class="text-uppercase mb-3"><i class="fa fa-user text-primary"></i> Thông Tin Khách Hàng</h5>
                                <p class="mb-1"><strong>Họ và Tên:</strong> <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></p>
                                <p class="mb-1"><strong>Số Điện Thoại:</strong> <?= htmlspecialchars($_SESSION['phone'] ?? '') ?></p>
                                <p class="mb-1"><strong>Địa Chỉ:</strong> <?= htmlspecialchars($_SESSION['address'] ?? '') ?></p>
                            </div>

                            <form method="post" class="mb-4">
                                <div class="bg-secondary border-inner p-4 rounded mb-4">
                                    <h5 class="text-white text-uppercase mb-3">Phương Thức Thanh Toán</h5>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="payment" id="payment1" checked>
                                        <label class="form-check-label" for="payment1">
                                            <i class="fa fa-credit-card text-primary"></i> Thanh toán khi nhận hàng (COD)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment" id="payment2">
                                        <label class="form-check-label" for="payment2">
                                            <i class="fa fa-money-bill text-primary"></i> Chuyển khoản ngân hàng
                                        </label>
                                    </div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" name="checkout" class="btn btn-primary border-inner py-3 text-uppercase fw-bold">
                                        <i class="fa fa-lock"></i> Hoàn Tất Thanh Toán
                                    </button>
                                    <a href="index.php" class="btn btn-outline-primary py-3">
                                        <i class="fa fa-arrow-left"></i> Quay Lại
                                    </a>
                                </div>
                            </form>

                        <?php else: ?>
                            <!-- Thông tin khách hàng sau khi thanh toán -->
                            <div class="text-center mb-5">
                                <i class="fa fa-check-circle text-success" style="font-size: 4rem;"></i>
                                <h3 class="text-white text-uppercase mt-3">Thanh Toán Thành Công!</h3>
                                <p class="text-secondary">Mã đơn hàng: <strong class="text-primary">#<?= $order_id ?></strong></p>
                            </div>

                            <div class="bg-secondary border-inner p-4 rounded mb-4">
                                <h5 class=" text-uppercase mb-3"><i class="fa fa-user text-primary"></i> Thông Tin Khách Hàng</h5>
                                <p class="mb-1"><strong>Họ và Tên:</strong> <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></p>
                                <p class="mb-1"><strong>Số Điện Thoại:</strong> <?= htmlspecialchars($_SESSION['phone'] ?? '') ?></p>
                                <p class="mb-1"><strong>Địa Chỉ:</strong> <?= htmlspecialchars($_SESSION['address'] ?? '') ?></p>
                            </div>

                            <div class="bg-secondary border-inner p-4 rounded mb-4">
                                <h5 class="text-white text-uppercase mb-3"><i class="fa fa-list text-primary"></i> Chi Tiết Đơn Hàng</h5>
                                <table class="table table-dark table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Sản phẩm</th>
                                            <th>Số lượng</th>
                                            <th>Giá</th>
                                            <th>Tổng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                                <td><?= $item['quantity'] ?></td>
                                                <td><?= number_format($item['price'],0,',','.') ?>₫</td>
                                                <td><?= number_format($item['price'] * $item['quantity'],0,',','.') ?>₫</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                                            <td><strong><?= number_format($total_amount,0,',','.') ?>₫</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="orders.php" class="btn btn-outline-primary w-100 py-3">
                                        <i class="fa fa-history"></i> Xem Lịch Sử Đơn Hàng
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="index.php" class="btn btn-primary border-inner w-100 py-3">
                                        <i class="fa fa-home"></i> Tiếp Tục Mua Sắm
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/main.js"></script>
</body>
</html>
