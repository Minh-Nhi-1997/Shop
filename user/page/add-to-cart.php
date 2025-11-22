<?php
session_start();
require './connect-db.php';

// Kiểm tra nếu người dùng đã đăng nhập
if (!isset($_SESSION['customer_id'])) {
    // Chưa đăng nhập -> chuyển hướng về login
    header("Location: login.html");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    die("Sản phẩm không hợp lệ.");
}

// Kiểm tra xem sản phẩm đã có trong giỏ hàng chưa
$stmt = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE customer_id = ? AND product_id = ?");
$stmt->bind_param("ii", $customer_id, $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Nếu có rồi -> tăng số lượng lên 1
    $row = $result->fetch_assoc();
    $new_quantity = $row['quantity'] + 1;

    $stmt_update = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ?");
    $stmt_update->bind_param("ii", $new_quantity, $row['cart_item_id']);
    $stmt_update->execute();
    $stmt_update->close();
} else {
    // Nếu chưa có -> thêm mới
    $stmt_insert = $conn->prepare("INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, 1)");
    $stmt_insert->bind_param("ii", $customer_id, $product_id);
    $stmt_insert->execute();
    $stmt_insert->close();
}

$stmt->close();

// Chuyển hướng trở lại trang trước hoặc trang sản phẩm
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>
