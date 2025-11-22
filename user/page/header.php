<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require './connect-db.php';
?>

<nav class="navbar navbar-expand-lg bg-dark navbar-dark shadow-sm py-3 py-lg-0 px-3 px-lg-0">
    <a href="index.html" class="navbar-brand d-block d-lg-none">
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
            <a href="about.html" class="nav-item nav-link">About Us</a>
            <a href="menu.html" class="nav-item nav-link">Menu & Pricing</a>
            <a href="team.html" class="nav-item nav-link">Master Chefs</a>

            <div class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Pages</a>
                <div class="dropdown-menu m-0">
                    <a href="service.html" class="dropdown-item">Our Service</a>
                    <a href="testimonial.html" class="dropdown-item">Testimonial</a>
                </div>
            </div>

            <a href="contact.html" class="nav-item nav-link">Contact Us</a>

            <?php if (isset($_SESSION['customer_id'])): ?>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                        <i class="fa fa-user me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
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
