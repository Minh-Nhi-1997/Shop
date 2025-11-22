<?php
session_start();
require './connect-db.php';

// Nhận dữ liệu từ form
$login_user = trim($_POST['login_user'] ?? '');
$login_pass = trim($_POST['login_pass'] ?? '');

// Kiểm tra rỗng
if ($login_user === '' || $login_pass === '') {
    die("Vui lòng nhập email và mật khẩu!");
}

// Không SELECT role nữa
$sql = "SELECT customer_id, full_name, email, password_hash
        FROM customers
        WHERE email = ? LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Lỗi prepare: " . $conn->error);
}

$stmt->bind_param("s", $login_user);
$stmt->execute();
$result = $stmt->get_result();

// Kiểm tra email tồn tại
if ($result->num_rows === 0) {
    die("Email hoặc mật khẩu không đúng!");
}

$row = $result->fetch_assoc();

// Kiểm tra mật khẩu
if (!password_verify($login_pass, $row['password_hash'])) {
    die("Email hoặc mật khẩu không đúng!");
}

// Đăng nhập thành công
$_SESSION['customer_id'] = $row['customer_id'];
$_SESSION['full_name']  = $row['full_name'];

// Không phân quyền nữa → tất cả vào index.php
header("Location: index.php");
exit;

$stmt->close();
$conn->close();
?>
