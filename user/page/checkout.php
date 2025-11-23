<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$msg = '';
$order_id = null;
$order_items = [];
$total_amount = 0;

// Lấy sản phẩm trong giỏ hàng để hiển thị trước khi đặt
$stmt = $conn->prepare("
    SELECT ci.product_id, ci.quantity, p.price, p.product_name, p.stock, p.image
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    WHERE ci.customer_id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$cart_items_display = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (empty($cart_items_display)) {
        $msg = "Giỏ hàng trống!";
    } else {
        // Kiểm tra tồn kho
        $out_of_stock = [];
        foreach ($cart_items_display as $item) {
            if ($item['stock'] <= 0 || $item['quantity'] > $item['stock']) {
                $out_of_stock[] = $item['product_name'];
            }
        }

        if (!empty($out_of_stock)) {
            $msg = "Không thể đặt hàng. Sản phẩm hết hàng hoặc vượt tồn kho: " . implode(', ', $out_of_stock);
        } else {
            // Tính tổng tiền
            foreach ($cart_items_display as $item) {
                $total_amount += $item['price'] * $item['quantity'];
            }

            // Thêm đơn hàng (status = pending)
            $stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, order_status, created_at) VALUES (?, ?, 'pending', NOW())");
            $stmt->bind_param("id", $uid, $total_amount);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            $stmt->close();

            // Thêm chi tiết đơn hàng và trừ tồn kho
            foreach ($cart_items_display as $item) {
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
                $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                $stmt->execute();
                $stmt->close();
            }

            // Xóa giỏ hàng
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE customer_id = ?");
            $stmt->bind_param("i", $uid);
            $stmt->execute();
            $stmt->close();

            $msg = "Đặt hàng thành công!";
        }
    }
}

// Lấy chi tiết đơn hàng nếu đã đặt
if ($order_id) {
    $stmt = $conn->prepare("
        SELECT oi.quantity, oi.price, p.product_name, p.image 
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
    <title>Đặt Hàng - CakeZone</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>

<div class="container-fluid bg-primary py-5 mb-5 hero-header">
    <div class="container py-5">
        <div class="row justify-content-start">
            <div class="col-lg-8 text-center text-lg-start">
                <h1 class="font-secondary text-primary mb-4">Place Order</h1>
                <h1 class="display-1 text-uppercase text-white mb-4">ĐẶT HÀNG</h1>
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
                        <div class="alert alert-<?= $order_id ? 'success' : 'danger' ?> alert-dismissible fade show border-inner" role="alert">
                            <?= htmlspecialchars($msg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$order_id): ?>
                        <h3 class="text-white text-uppercase mb-4">
                            <i class="fa fa-shopping-cart text-primary"></i> Xác Nhận Đơn Hàng
                        </h3>

                        <div class="bg-secondary border-inner p-4 rounded mb-4">
                            <h5 class="text-uppercase mb-3"><i class="fa fa-user text-primary"></i> Thông Tin Khách Hàng</h5>
                            <p class="mb-1"><strong>Họ và Tên:</strong> <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></p>
                            <p class="mb-1"><strong>Số Điện Thoại:</strong> <?= htmlspecialchars($_SESSION['phone'] ?? '') ?></p>
                            <p class="mb-1"><strong>Địa Chỉ:</strong> <?= htmlspecialchars($_SESSION['address'] ?? '') ?></p>
                        </div>

                        <!-- Hiển thị sản phẩm giỏ hàng trước khi đặt -->
                        <div class="bg-secondary border-inner p-4 rounded mb-4">
                            <h5 class="text-uppercase mb-3"><i class="fa fa-list text-primary"></i> Sản Phẩm Trong Giỏ Hàng</h5>
                            <?php if (!empty($cart_items_display)): ?>
                                <table class="table table-dark table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Hình Ảnh</th>
                                            <th>Sản phẩm</th>
                                            <th>Số lượng</th>
                                            <th>Giá</th>
                                            <th>Tổng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $subtotal = 0;
                                        foreach ($cart_items_display as $item):
                                            $total_item = $item['price'] * $item['quantity'];
                                            $subtotal += $total_item;
                                        ?>
                                        <tr>
                                            <td><img src="../../assets/img/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" style="width:50px; height:50px; object-fit:cover;"></td>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td><?= number_format($item['price'],0,',','.') ?>₫</td>
                                            <td><?= number_format($total_item,0,',','.') ?>₫</td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Tạm tính:</strong></td>
                                            <td><strong><?= number_format($subtotal,0,',','.') ?>₫</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-white">Giỏ hàng trống!</p>
                            <?php endif; ?>
                        </div>

                        <form method="post" class="mb-4">
                            <div class="d-grid gap-2">
                                <button type="submit" name="place_order" class="btn btn-primary border-inner py-3 text-uppercase fw-bold">
                                    <i class="fa fa-shopping-bag"></i> Đặt Hàng Ngay
                                </button>
                                <a href="cart.php" class="btn btn-outline-primary py-3">
                                    <i class="fa fa-arrow-left"></i> Quay Lại Giỏ Hàng
                                </a>
                            </div>
                        </form>

                    <?php else: ?>
                        <div class="text-center mb-5">
                            <i class="fa fa-check-circle text-success" style="font-size: 4rem;"></i>
                            <h3 class="text-white text-uppercase mt-3">Đặt Hàng Thành Công!</h3>
                            <p class="text-secondary">Mã đơn hàng: <strong class="text-primary">#<?= $order_id ?></strong></p>
                        </div>

                        <div class="bg-secondary border-inner p-4 rounded mb-4">
                            <h5 class="text-uppercase mb-3"><i class="fa fa-user text-primary"></i> Thông Tin Khách Hàng</h5>
                            <p class="mb-1"><strong>Họ và Tên:</strong> <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></p>
                            <p class="mb-1"><strong>Số Điện Thoại:</strong> <?= htmlspecialchars($_SESSION['phone'] ?? '') ?></p>
                            <p class="mb-1"><strong>Địa Chỉ:</strong> <?= htmlspecialchars($_SESSION['address'] ?? '') ?></p>
                        </div>

                        <div class="bg-secondary border-inner p-4 rounded mb-4">
                            <h5 class="text-uppercase mb-3"><i class="fa fa-list text-primary"></i> Chi Tiết Đơn Hàng</h5>
                            <table class="table table-dark table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Hình Ảnh</th>
                                        <th>Sản phẩm</th>
                                        <th>Số lượng</th>
                                        <th>Giá</th>
                                        <th>Tổng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td><img src="../../assets/img/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>" style="width:50px; height:50px; object-fit:cover;"></td>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td><?= number_format($item['price'],0,',','.') ?>₫</td>
                                            <td><?= number_format($item['price'] * $item['quantity'],0,',','.') ?>₫</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="4" class="text-end"><strong>Tổng cộng:</strong></td>
                                        <td><strong><?= number_format($total_amount,0,',','.') ?>₫</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="lichSuDonHang.php" class="btn btn-outline-primary w-100 py-3">
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="../../assets/js/main.js"></script>
</body>
</html>
