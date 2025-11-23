<?php
session_start();
require '../../user/page/connect-db.php';

$error = ''; // Biến lưu thông báo lỗi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_user = trim($_POST['login_user'] ?? '');
    $login_pass = trim($_POST['login_pass'] ?? '');

    if ($login_user === '' || $login_pass === '') {
        $error = "Vui lòng nhập email và mật khẩu!";
    } else {
        $sql = "SELECT admin_id, full_name, email, password_hash 
                FROM admins 
                WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error = "Lỗi hệ thống: " . $conn->error;
        } else {
            $stmt->bind_param("s", $login_user);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $error = "Email hoặc mật khẩu không đúng!";
            } else {
                $row = $result->fetch_assoc();
                if (!password_verify($login_pass, $row['password_hash'])) {
                    $error = "Email hoặc mật khẩu không đúng!";
                } else {
                    $_SESSION['admin_id']  = $row['admin_id'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['is_admin']  = true;
                    header("Location: admin-dashboard.php");
                    exit;
                }
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Đăng nhập - Admin - CakeZone</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link
        href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Oswald:wght@500;600;700&family=Pacifico&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .auth-card { max-width: 420px; margin: 90px auto; }
        .brand-logo { font-family: 'Oswald', sans-serif; }
    </style>
</head>

<body class="bg-img">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand text-white brand-logo" href="index.html"><i
                    class="fa fa-birthday-cake text-primary me-2"></i>CakeZone</a>
        </div>
    </nav>

    <div class="container auth-card">
        <div class="card border-inner">
            <div class="card-body p-4">
                <h3 class="mb-3 text-center">Đăng nhập ADMIN</h3>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form id="loginForm" method="POST" action="" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input id="loginUser" type="text" class="form-control" name="login_user"
                            placeholder="Email hoặc username"
                            value="<?= htmlspecialchars($_POST['login_user'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <div class="input-group">
                            <input id="loginPass" type="password" class="form-control" name="login_pass"
                                placeholder="Mật khẩu" required>
                            <button id="toggleLoginPass" type="button" class="btn btn-outline-secondary">Hiện</button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 border-inner">Đăng nhập</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script>
        // Toggle hiển thị mật khẩu
        $('#toggleLoginPass').on('click', function () {
            let passField = $('#loginPass');
            if (passField.attr('type') === 'password') {
                passField.attr('type', 'text');
                $(this).text('Ẩn');
            } else {
                passField.attr('type', 'password');
                $(this).text('Hiện');
            }
        });
    </script>
</body>
</html>
