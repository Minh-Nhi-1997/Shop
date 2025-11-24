<?php
session_start();
require './connect-db.php'; // kết nối đến database

// ---- Nhận dữ liệu từ form ----
$full_name = trim($_POST['full_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$password  = trim($_POST['password'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$address   = trim($_POST['address'] ?? '');

// ---- Kiểm tra dữ liệu bắt buộc ----
if ($full_name == '' || $email == '' || $password == '') {
    die("Vui lòng nhập đầy đủ thông tin bắt buộc!");
}

// ---- Kiểm tra email hợp lệ ----
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Email không hợp lệ!");
}

// ---- Kiểm tra mật khẩu >= 8 ký tự ----
if (strlen($password) < 8) {
    die("Mật khẩu phải từ 8 ký tự trở lên!");
}

// ---- Kiểm tra email trùng ----
$stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ?");
if (!$stmt) {
    die("Lỗi prepare (check email): " . $conn->error);
}

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    die("Email đã được sử dụng!");
}
$stmt->close();

// ---- Mã hóa mật khẩu ----
$hash = password_hash($password, PASSWORD_DEFAULT);

// ---- Lưu vào database ----
$stmt = $conn->prepare("
    INSERT INTO customers (full_name, email, password_hash, phone, address)
    VALUES (?, ?, ?, ?, ?)
");
if (!$stmt) {
    die("Lỗi prepare (insert): " . $conn->error);
}

$stmt->bind_param("sssss", $full_name, $email, $hash, $phone, $address);

if ($stmt->execute()) {
    echo "<h3>Đăng ký thành công!</h3>";
    echo "<a href='login.php'>→ Đăng nhập ngay</a>";
} else {
    echo "Lỗi khi lưu dữ liệu: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
