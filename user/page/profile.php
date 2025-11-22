<?php
session_start();
require './connect-db.php'; // Đảm bảo file này tồn tại và kết nối DB chính xác

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$msg = '';
$err = '';

// Lấy dữ liệu cũ từ DB
$stmt = $conn->prepare("SELECT full_name, email, phone, address, created_at FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc() ?: [];
$stmt->close();

// Xử lý form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    // Kiểm tra dữ liệu
    if ($full_name === '' || $email === '') {
        $err = 'Vui lòng nhập tên và email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Email không hợp lệ.';
    } else {
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? AND customer_id <> ?");
        $stmt->bind_param("si", $email, $uid);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $err = 'Email đã được sử dụng.';
        }
        $stmt->close();
    }

    // Xử lý đổi mật khẩu
    $changePwd = false;
    if (empty($err) && !empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];

        $stmt = $conn->prepare("SELECT password_hash FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $err = 'Mật khẩu hiện tại không đúng.';
        } elseif (strlen($new) < 8) {
            $err = 'Mật khẩu mới phải từ 8 ký tự trở lên.';
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $changePwd = true;
        }
    }

    // Cập nhật thông tin
    if (empty($err)) {
        if ($changePwd) {
            $stmt = $conn->prepare("UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ?, password_hash = ? WHERE customer_id = ?");
            $stmt->bind_param("sssssi", $full_name, $email, $phone, $address, $new_hash, $uid);
        } else {
            $stmt = $conn->prepare("UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ? WHERE customer_id = ?");
            $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $uid);
        }

        if (!$stmt->execute()) {
            $err = 'Lỗi cập nhật: ' . $stmt->error;
        } else {
            $msg = 'Cập nhật thông tin thành công.';
            $_SESSION['full_name'] = $full_name;
            // Cập nhật $user để hiển thị dữ liệu mới ngay lập tức
            $user['full_name'] = $full_name;
            $user['email'] = $email;
            $user['phone'] = $phone;
            $user['address'] = $address;
        }
        if ($stmt) $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Thông tin cá nhân - CakeZone</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="../../assests/img/favicon.ico" rel="icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Oswald:wght@500;600;700&family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assests/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assests/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Topbar Start -->
    <div class="container-fluid px-0 d-none d-lg-block">
        <div class="row gx-0">
            <div class="col-lg-4 text-center bg-secondary py-3">
                <div class="d-inline-flex align-items-center justify-content-center">
                    <i class="bi bi-envelope fs-1 text-primary me-3"></i>
                    <div class="text-start">
                        <h6 class="text-uppercase mb-1">Email Us</h6>
                        <span>info@example.com</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-center bg-primary border-inner py-3">
                <div class="d-inline-flex align-items-center justify-content-center">
                    <a href="index.php" class="navbar-brand">
                        <h1 class="m-0 text-uppercase text-white"><i class="fa fa-birthday-cake fs-1 text-dark me-3"></i>CakeZone</h1>
                    </a>
                </div>
            </div>
            <div class="col-lg-4 text-center bg-secondary py-3">
                <div class="d-inline-flex align-items-center justify-content-center">
                    <i class="bi bi-phone-vibrate fs-1 text-primary me-3"></i>
                    <div class="text-start">
                        <h6 class="text-uppercase mb-1">Call Us</h6>
                        <span>+012 345 6789</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Topbar End -->

    <!-- Navbar Start -->
    <?php include 'header.php'; ?>
    <!-- Navbar End -->

    <!-- Page Header Start -->
    <div class="container-fluid bg-primary py-5 mb-5 hero-header">
        <div class="container py-5">
            <div class="row justify-content-start">
                <div class="col-lg-8 text-center text-lg-start">
                    <h1 class="font-secondary text-primary mb-4">My Account</h1>
                    <h1 class="display-1 text-uppercase text-white mb-4">Thông Tin Cá Nhân</h1>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Profile Section Start -->
    <div class="container-fluid py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="bg-dark border-inner p-5 rounded">
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <h3 class="text-white text-uppercase">Thông Tin Của Bạn</h3>
                            <a href="logout.php" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-sign-out-alt"></i> Đăng Xuất
                            </a>
                        </div>

                        <?php if ($msg): ?>
                            <div class="alert alert-success border-inner mb-4">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($msg); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($err): ?>
                            <div class="alert alert-danger border-inner mb-4">
                                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($err); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="row g-3 mb-5">
                            <div class="col-md-6">
                                <label class="form-label text-white fw-bold">Họ và Tên</label>
                                <input name="full_name" class="form-control border-inner" required value="<?= htmlspecialchars($_POST['full_name'] ?? $user['full_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white fw-bold">Email</label>
                                <input name="email" type="email" class="form-control border-inner" required value="<?= htmlspecialchars($_POST['email'] ?? $user['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white fw-bold">Số Điện Thoại</label>
                                <input name="phone" class="form-control border-inner" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white fw-bold">Ngày Tạo Tài Khoản</label>
                                <input type="text" class="form-control border-inner" disabled value="<?= htmlspecialchars($user['created_at'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white fw-bold">Địa Chỉ</label>
                                <textarea name="address" class="form-control border-inner" rows="3"><?= htmlspecialchars($_POST['address'] ?? $user['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="col-12">
                                <hr class="bg-secondary">
                            </div>

                            <div class="col-12">
                                <h5 class="text-primary text-uppercase mb-3">Đổi Mật Khẩu (Không Bắt Buộc)</h5>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white fw-bold">Mật Khẩu Hiện Tại</label>
                                <input name="current_password" type="password" class="form-control border-inner" placeholder="Nhập mật khẩu hiện tại">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white fw-bold">Mật Khẩu Mới</label>
                                <input name="new_password" type="password" class="form-control border-inner" placeholder="Mật khẩu mới (>=8 ký tự)">
                            </div>

                            <div class="col-12 text-end pt-4">
                                <a href="index.php" class="btn btn-outline-primary me-2">
                                    <i class="fa fa-arrow-left"></i> Quay Lại
                                </a>
                                <button type="submit" class="btn btn-primary border-inner">
                                    <i class="fa fa-save"></i> Lưu Thay Đổi
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Additional Info Card -->
                    <div class="bg-secondary border-inner p-5 rounded mt-4">
                        <h5 class="text-white text-uppercase mb-3">Hỗ Trợ Tài Khoản</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary border-inner d-flex align-items-center justify-content-center mb-0" style="width: 50px; height: 50px;">
                                        <i class="fa fa-history text-white"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="text-white mb-1">Lịch Sử Đơn Hàng</h6>
                                        <a href="orders.php" class="text-primary small">Xem các đơn hàng của bạn</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary border-inner d-flex align-items-center justify-content-center mb-0" style="width: 50px; height: 50px;">
                                        <i class="fa fa-heart text-white"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="text-white mb-1">Sản Phẩm Yêu Thích</h6>
                                        <a href="wishlist.php" class="text-primary small">Quản lý danh sách yêu thích</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer + Scripts giống file gốc -->
    <?php include 'footer.php'; ?>
</body>
</html>
