<?php
session_start();
require './connect-db.php';

// Nếu chưa đăng nhập, chuyển đến login
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.html");
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$msg = '';

// Xóa sản phẩm khỏi giỏ hàng
if (isset($_GET['remove'])) {
    $cart_item_id = (int)$_GET['remove'];
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $cart_item_id, $uid);
    $stmt->execute();
    $stmt->close();
    $msg = 'Sản phẩm đã được xóa khỏi giỏ hàng.';
}

// Cập nhật số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantities'])) {
    foreach ($_POST['quantities'] as $cart_item_id => $quantity) {
        $quantity = max(1, (int)$quantity); // đảm bảo >=1
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND customer_id = ?");
        $stmt->bind_param("iii", $quantity, $cart_item_id, $uid);
        $stmt->execute();
        $stmt->close();
    }
    $msg = 'Giỏ hàng đã được cập nhật.';
}

// Lấy sản phẩm trong giỏ hàng
$stmt = $conn->prepare("
    SELECT ci.cart_item_id, ci.quantity, p.product_name, p.price, p.image
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    WHERE ci.customer_id = ?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Tính tổng tiền
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ Hàng - CakeZone</title>
    <link href="../../assests/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assests/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <h2 class="mb-4">Giỏ Hàng Của Bạn</h2>

        <?php if ($msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <p>Giỏ hàng của bạn đang trống. <a href="index.php">Quay lại mua sắm</a></p>
        <?php else: ?>
            <form method="post">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Hình ảnh</th>
                            <th>Giá</th>
                            <th>Số lượng</th>
                            <th>Thành tiền</th>
                            <th>Xóa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><img src="../../assests/img/<?= htmlspecialchars($item['image']) ?>" width="60" alt=""></td>
                                <td><?= number_format($item['price'], 0, ',', '.') ?>₫</td>
                                <td>
                                    <input type="number" name="quantities[<?= $item['cart_item_id'] ?>]" value="<?= $item['quantity'] ?>" min="1" class="form-control" style="width:80px;">
                                </td>
                                <td><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>₫</td>
                                <td>
                                    <a href="?remove=<?= $item['cart_item_id'] ?>" class="btn btn-danger btn-sm">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-secondary">
                            <td colspan="4" class="text-end fw-bold">Tổng tiền:</td>
                            <td colspan="2" class="fw-bold"><?= number_format($total, 0, ',', '.') ?>₫</td>
                        </tr>
                    </tbody>
                </table>

                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Tiếp tục mua sắm</a>
                    <div>
                        <button type="submit" class="btn btn-primary me-2"><i class="fa fa-sync"></i> Cập nhật giỏ hàng</button>
                        <a href="checkout.php" class="btn btn-success"><i class="fa fa-credit-card"></i> Thanh toán</a>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="../../assests/js/bootstrap.bundle.min.js"></script>
</body>
</html>
