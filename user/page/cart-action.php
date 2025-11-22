<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) exit(json_encode(['success'=>false]));
$uid = (int)$_SESSION['customer_id'];

$action = $_POST['action'] ?? '';
$cart_item_id = (int)($_POST['cart_item_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

if($action == 'update'){
    $stmt = $conn->prepare("UPDATE cart_items SET quantity=? WHERE cart_item_id=? AND customer_id=?");
    $stmt->bind_param("iii", $quantity, $cart_item_id, $uid);
    $stmt->execute();
    $stmt->close();
}
elseif($action == 'remove'){
    $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id=? AND customer_id=?");
    $stmt->bind_param("ii", $cart_item_id, $uid);
    $stmt->execute();
    $stmt->close();
}

// Lấy subtotal và tổng tiền
$stmt = $conn->prepare("
    SELECT ci.cart_item_id, ci.quantity, p.price
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    WHERE ci.customer_id=?
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

$total = 0;
$subtotal = 0;
foreach($cart_items as $item){
    if($item['cart_item_id']==$cart_item_id) $subtotal = $item['price'] * $item['quantity'];
    $total += $item['price'] * $item['quantity'];
}

echo json_encode([
    'success'=>true,
    'subtotal'=>number_format($subtotal,0,',','.'),
    'total'=>number_format($total,0,',','.')
]);
