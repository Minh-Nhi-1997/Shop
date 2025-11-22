<?php
session_start();
require './connect-db.php';

// Nếu chưa đăng nhập, redirect tới login
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.html");
    exit;
}

$uid = (int)$_SESSION['customer_id'];

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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ Hàng - CakeZone</title>
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php include 'header.php'; ?>

<div class="container py-5">
    <h2 class="mb-4">Giỏ Hàng Của Bạn</h2>

    <?php if (empty($cart_items)): ?>
        <p>Giỏ hàng của bạn đang trống. <a href="index.php">Quay lại mua sắm</a></p>
    <?php else: ?>
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
            <tbody id="cart-body">
                <?php foreach ($cart_items as $item): ?>
                    <tr data-id="<?= $item['cart_item_id'] ?>">
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td><img src="../../assets/img/<?= htmlspecialchars($item['image']) ?>" width="60" alt="<?= htmlspecialchars($item['product_name']) ?>"></td>
                        <td class="price"><?= number_format($item['price'], 0, ',', '.') ?>₫</td>
                        <td>
                            <input type="number" class="form-control quantity" value="<?= $item['quantity'] ?>" min="1" style="width:80px;">
                        </td>
                        <td class="subtotal"><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>₫</td>
                        <td>
                            <button class="btn btn-danger btn-sm remove-btn"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary">
                    <td colspan="4" class="text-end fw-bold">Tổng tiền:</td>
                    <td colspan="2" class="fw-bold" id="total">
                        <?php 
                        $total = 0;
                        foreach ($cart_items as $item) $total += $item['price'] * $item['quantity'];
                        echo number_format($total, 0, ',', '.') . '₫';
                        ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="d-flex justify-content-between">
            <a href="index.php" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Tiếp tục mua sắm</a>
            <a href="checkout.php" class="btn btn-success"><i class="fa fa-credit-card"></i> Thanh toán</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Cập nhật số lượng
$(document).on('change', '.quantity', function(){
    let tr = $(this).closest('tr');
    let cart_id = tr.data('id');
    let qty = $(this).val();

    $.post('cart-action.php', {action:'update', cart_item_id: cart_id, quantity: qty}, function(data){
        if(data.success){
            tr.find('.subtotal').text(data.subtotal + '₫');
            $('#total').text(data.total + '₫');
        }
    }, 'json');
});

// Xóa sản phẩm
$(document).on('click', '.remove-btn', function(){
    if(!confirm('Bạn có chắc muốn xóa sản phẩm này?')) return;
    let tr = $(this).closest('tr');
    let cart_id = tr.data('id');

    $.post('cart-action.php', {action:'remove', cart_item_id: cart_id}, function(data){
        if(data.success){
            tr.remove();
            $('#total').text(data.total + '₫');
        }
    }, 'json');
});
</script>
</body>
</html>
