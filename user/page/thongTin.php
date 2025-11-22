<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$msg = '';
$err = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    // validate required
    if ($full_name === '' || $email === '') {
        $err = 'Vui lòng nhập tên và email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Email không hợp lệ.';
    } else {
        // check email uniqueness
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE email = ? AND customer_id <> ?");
        $stmt->bind_param("si", $email, $uid);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $err = 'Email đã được sử dụng bởi người khác.';
        }
        $stmt->close();
    }

    // handle password change if requested
    $changePwd = false;
    if (empty($err) && !empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];

        // fetch current hash
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

    // perform update
    if (empty($err)) {
        if ($changePwd) {
            $stmt = $conn->prepare("UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ?, password_hash = ? WHERE customer_id = ?");
            $stmt->bind_param("sssssi", $full_name, $email, $phone, $address, $new_hash, $uid);
        } else {
            $stmt = $conn->prepare("UPDATE customers SET full_name = ?, email = ?, phone = ?, address = ? WHERE customer_id = ?");
            $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $uid);
        }

        if (!$stmt) {
            $err = 'Lỗi chuẩn bị truy vấn: ' . $conn->error;
        } elseif (!$stmt->execute()) {
            $err = 'Lỗi cập nhật: ' . $stmt->error;
        } else {
            $msg = 'Cập nhật thông tin thành công.';
            // refresh session name if stored
            $_SESSION['full_name'] = $full_name;
            // redirect to avoid resubmit
            header("Location: profile.php?updated=1");
            exit;
        }
        if ($stmt) $stmt->close();
    }
}

// fetch user
$stmt = $conn->prepare("SELECT full_name, email, phone, address, created_at FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc() ?: [];
$stmt->close();
$conn->close();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Thông tin cá nhân - CakeZone</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
<link href="../assests/css/bootstrap.min.css" rel="stylesheet">
<link href="../assests/css/style.css" rel="stylesheet">
<style>
.container-profile{max-width:900px;margin:40px auto}
.profile-card{padding:20px;border-radius:8px;background:#fff;border:1px solid #eee}
.field-label{font-weight:600}
</style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="container container-profile">
  <div class="profile-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Thông tin cá nhân</h4>
      <a href="logout.php" class="btn btn-sm btn-outline-secondary">Đăng xuất</a>
    </div>

    <?php if (!empty($_GET['updated'])): ?>
      <div class="alert alert-success">Cập nhật thông tin thành công.</div>
    <?php endif; ?>
    <?php if ($msg): ?>
      <div class="alert alert-success"><?= htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-3">
      <div class="col-md-6">
        <label class="field-label">Họ và tên</label>
        <input name="full_name" class="form-control" required value="<?= htmlspecialchars($user['full_name'] ?? ''); ?>">
      </div>
      <div class="col-md-6">
        <label class="field-label">Email</label>
        <input name="email" type="email" class="form-control" required value="<?= htmlspecialchars($user['email'] ?? ''); ?>">
      </div>
      <div class="col-md-6">
        <label class="field-label">Số điện thoại</label>
        <input name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? ''); ?>">
      </div>
      <div class="col-12">
        <label class="field-label">Địa chỉ</label>
        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($user['address'] ?? ''); ?></textarea>
      </div>

      <div class="col-12"><hr></div>

      <div class="col-12"><h6>Đổi mật khẩu (không bắt buộc)</h6></div>
      <div class="col-md-6">
        <label class="field-label">Mật khẩu hiện tại</label>
        <input name="current_password" type="password" class="form-control" placeholder="Nhập mật khẩu hiện tại">
      </div>
      <div class="col-md-6">
        <label class="field-label">Mật khẩu mới</label>
        <input name="new_password" type="password" class="form-control" placeholder="Mật khẩu mới (>=8 ký tự)">
      </div>

      <div class="col-12 text-end">
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
        <a href="index.php" class="btn btn-secondary">Hủy</a>
      </div>
    </form>

    <div class="mt-3 text-muted small">
      Tạo tài khoản: <?= htmlspecialchars($user['created_at'] ?? ''); ?>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="../assests/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```// filepath: /Applications/XAMPP/xamppfiles/htdocs/Shop/page/profile.php