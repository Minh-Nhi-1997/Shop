<?php
session_start();
require 'connect.php'; // file bạn đã tạo

// ---- Nhận dữ liệu từ form ----
$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$address   = trim($_POST['address'] ?? '');
$username  = trim($_POST['username'] ?? '');
$password  = trim($_POST['password'] ?? '');

// ---- Kiểm tra dữ liệu bắt buộc ----
if ($full_name == '' || $email == '' || $username == '' || $password == '') {
    die("Vui lòng nhập đầy đủ thông tin bắt buộc!");
}

// ---- Kiểm tra email có đúng định dạng ----
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Email không hợp lệ!");
}

// ---- Kiểm tra mật khẩu >= 8 ký tự ----
if (strlen($password) < 8) {
    die("Mật khẩu phải từ 8 ký tự trở lên!");
}

// ---- Kiểm tra email trùng ----
$stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die("Email đã được sử dụng!");
}

// ---- Kiểm tra username trùng ----
$stmt = $conn->prepare("SELECT customer_id FROM customers WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die("Tên đăng nhập đã tồn tại!");
}

// ---- Mã hóa mật khẩu ----
$hash = password_hash($password, PASSWORD_DEFAULT);

// ---- Lưu vào database ----
$stmt = $conn->prepare("
    INSERT INTO customers (full_name, email, password_hash, phone, address, username)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssss", $full_name, $email, $hash, $phone, $address, $username);

if ($stmt->execute()) {
    echo "<h3>Đăng ký thành công!</h3>";
    echo "<a href='login.php'>→ Đăng nhập ngay</a>";
} else {
    echo "Lỗi: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
