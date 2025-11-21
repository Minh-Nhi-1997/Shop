<?php
session_start();
require './connect-db.php';

$login_user = trim($_POST['login_user'] ?? '');
$login_pass = trim($_POST['login_pass'] ?? '');

if ($login_user == '' || $login_pass == '') {
    die("Vui lòng nhập email và mật khẩu!");
}

// Lấy thêm role
$stmt = $conn->prepare("SELECT customer_id, full_name, password_hash, role FROM customers WHERE email = ?");
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
        $_SESSION['full_name'] = $row['full_name'];
        $_SESSION['role'] = $row['role']; // <-- Lưu role vào session

        // Chuyển hướng theo role
        if ($row['role'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: index.php");
        }
        exit;
    }
}

die("Email hoặc mật khẩu không đúng!");
$stmt->close();
$conn->close();
