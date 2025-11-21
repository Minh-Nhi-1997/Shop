<?php
session_start();
require './connect-db.php'; // Kết nối đến database

// ---- Nhận dữ liệu từ form ----
$email_or_username = trim($_POST['loginUser'] ?? '');
$password          = trim($_POST['loginPass'] ?? '');
$remember          = isset($_POST['remember']) ? true : false;

// ---- Kiểm tra dữ liệu bắt buộc ----
if ($email_or_username == '' || $password == '') {
    die("Vui lòng nhập đầy đủ thông tin!");
}

// ---- Tìm user theo email (vì bảng hiện tại chỉ có email) ----
$stmt = $conn->prepare("SELECT customer_id, full_name, email, password_hash FROM customers WHERE email = ?");
if (!$stmt) {
    die("Lỗi prepare: " . $conn->error);
}

$stmt->bind_param("s", $email_or_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Email không tồn tại!");
}

$user = $result->fetch_assoc();
$stmt->close();

// ---- Kiểm tra mật khẩu ----
if (!password_verify($password, $user['password_hash'])) {
    die("Mật khẩu không đúng!");
}

// ---- Đăng nhập thành công ----
$_SESSION['customer_id'] = $user['customer_id'];
$_SESSION['full_name']   = $user['full_name'];
$_SESSION['email']       = $user['email'];

// ---- Xử lý ghi nhớ (cookie) nếu cần ----
if ($remember) {
    setcookie('customer_id', $user['customer_id'], time() + (30 * 24 * 60 * 60), "/"); // 30 ngày
}

// ---- Chuyển hướng sau khi đăng nhập ----
header("Location: index.php"); // hoặc trang dashboard
exit();
?>
