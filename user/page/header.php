<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require './connect-db.php';

// Lấy số lượng giỏ hàng của user nếu đã đăng nhập
$cart_count = 0;
if (isset($_SESSION['customer_id'])) {
    $uid = (int)$_SESSION['customer_id'];
    $stmt = $conn->prepare("SELECT SUM(quantity) AS total FROM cart_items WHERE customer_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $cart_count = (int)($row['total'] ?? 0);
    $stmt->close();
}
?>
<!-- Topbar Start -->
    <div class="container-fluid px-0 d-none d-lg-block">
        <div class="row gx-0">
            <div class="col-lg-4 text-center bg-secondary py-3">
                <div class="d-inline-flex align-items-center justify-content-center">
                    <i class="bi bi-envelope fs-1 text-primary me-3"></i>
                    <div class="text-start">
                        <h6 class="text-uppercase mb-1">Email Us</h6>
                        <span>nfo@itc.edu.vn</span>
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
                        <span>(028) 397 349 83</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Topbar End -->
<nav class="navbar navbar-expand-lg bg-dark navbar-dark shadow-sm py-3 py-lg-0 px-3 px-lg-0">
    <a href="index.php" class="navbar-brand d-block d-lg-none">
        <h1 class="m-0 text-uppercase text-white">
            <i class="fa fa-birthday-cake fs-1 text-primary me-3"></i>CakeZone
        </h1>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarCollapse">
        <div class="navbar-nav ms-auto mx-lg-auto py-0">
            <a href="index.php" class="nav-item nav-link active">Home</a>
            <a href="about.php" class="nav-item nav-link">About Us</a>
            <a href="menu.php" class="nav-item nav-link">Menu & Pricing</a>
            <a href="team.php" class="nav-item nav-link">Master Chefs</a>

            <div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Pages</a>
                <div class="dropdown-menu m-0">
                    <a href="service.php" class="dropdown-item">Our Service</a>
                    <a href="testimonial.php" class="dropdown-item">Testimonial</a>
                </div>
            </div>

            <a href="contact.php" class="nav-item nav-link">Contact Us</a>

            <!-- Giỏ hàng -->
            <a href="cart.php" class="nav-item nav-link position-relative">
                <i class="fa fa-shopping-cart me-1"></i> Cart
                <?php if ($cart_count > 0): ?>
                    <span class="badge bg-danger position-absolute top-0 start-100 translate-middle"><?= $cart_count ?></span>
                <?php endif; ?>
            </a>

            <!-- User Dropdown -->
            <?php if (isset($_SESSION['customer_id'])): ?>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                        <i class="fa fa-user me-2"></i>
                        <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end m-0">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fa fa-id-card me-2"></i>Thông tin cá nhân
                        </a>
                        <a href="logout.php" class="dropdown-item text-danger">
                            <i class="fa fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.html" class="nav-item nav-link">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
