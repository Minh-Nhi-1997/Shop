<?php
session_start();
require '../../user/page/connect-db.php'; // chỉnh đường dẫn tới file kết nối DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$full_name || !$email || !$password) {
        die("Vui lòng nhập đủ thông tin tên, email và mật khẩu!");
    }

    // Hash mật khẩu
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Kiểm tra email đã tồn tại chưa
    $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        die("Email này đã tồn tại!");
    }

    // Thêm admin mới
    $stmt = $conn->prepare("INSERT INTO admins (full_name, email, password_hash, phone, address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $full_name, $email, $password_hash, $phone, $address);

    if ($stmt->execute()) {
        echo "Tạo admin mới thành công! <br>";
        echo "Email: $email <br> Mật khẩu: $password";
    } else {
        echo "Lỗi: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo Admin Mới</title>
</head>
<body>
<h2>Tạo Admin Mới</h2>
<form method="post">
    <label>Họ và tên: <input type="text" name="full_name" required></label><br><br>
    <label>Email: <input type="email" name="email" required></label><br><br>
    <label>Mật khẩu: <input type="text" name="password" required></label><br><br>
    <label>Điện thoại: <input type="text" name="phone"></label><br><br>
    <label>Địa chỉ: <input type="text" name="address"></label><br><br>
    <button type="submit">Tạo Admin</button>
</form>
</body>
</html>
