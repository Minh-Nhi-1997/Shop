<?php
session_start();
require './connect-db.php';

// Nhận dữ liệu từ form
$login_user = trim($_POST['login_user'] ?? '');
$login_pass = trim($_POST['login_pass'] ?? '');

// Kiểm tra dữ liệu bắt buộc
if ($login_user == '' || $login_pass == '') {
    die("Vui lòng nhập email và mật khẩu!");
}

// Tìm customer theo email
$stmt = $conn->prepare("SELECT customer_id, password_hash FROM customers WHERE email = ?");
if (!$stmt) {
    die("Lỗi prepare: " . $conn->error);
}

$stmt->bind_param("s", $login_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (password_verify($login_pass, $row['password_hash'])) {
        $_SESSION['customer_id'] = $row['customer_id'];
        header("Location: index.html");
        exit;
    }
}

die("Email hoặc mật khẩu không đúng!");
$stmt->close();
$conn->close();
?>