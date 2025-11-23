<?php
session_start();
require './connect-db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Bạn cần đăng nhập để thêm vào giỏ hàng!'
    ]);
    exit;
}

$customer_id = $_SESSION['customer_id'];
$product_id = intval($_POST['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không hợp lệ.']);
    exit;
}

// Kiểm tra sản phẩm đã có trong giỏ hàng chưa
$stmt = $conn->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE customer_id=? AND product_id=?");
$stmt->bind_param("ii", $customer_id, $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $new_quantity = $row['quantity'] + 1;

    $stmt_update = $conn->prepare("UPDATE cart_items SET quantity=? WHERE cart_item_id=?");
    $stmt_update->bind_param("ii", $new_quantity, $row['cart_item_id']);
    $stmt_update->execute();
    $stmt_update->close();
} else {
    $stmt_insert = $conn->prepare("INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, 1)");
    $stmt_insert->bind_param("ii", $customer_id, $product_id);
    $stmt_insert->execute();
    $stmt_insert->close();
}
$stmt->close();

// Lấy tổng số sản phẩm trong giỏ hàng
$stmt_count = $conn->prepare("SELECT SUM(quantity) AS total FROM cart_items WHERE customer_id=?");
$stmt_count->bind_param("i", $customer_id);
$stmt_count->execute();
$total_cart = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
$stmt_count->close();

echo json_encode([
    'success' => true,
    'cart_count' => $total_cart,
    'message' => 'Đã thêm sản phẩm vào giỏ hàng!'
]);
