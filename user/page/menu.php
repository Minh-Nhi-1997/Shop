<?php
session_start();
require './connect-db.php';
// Nếu chưa đăng nhập, redirect tới login
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CakeZone - Cake Shop Website Template</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="Free HTML Templates" name="keywords">
    <meta content="Free HTML Templates" name="description">

    <!-- Favicon -->
    <link href="../../assets/img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Oswald:wght@500;600;700&family=Pacifico&display=swap" rel="stylesheet"> 

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="../../assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Navbar Start -->
    <?php include 'header.php'; ?>
    <!-- Navbar End -->


    <!-- Page Header Start -->
    <div class="container-fluid bg-dark bg-img p-5 mb-5">
        <div class="row">
            <div class="col-12 text-center">
                <h1 class="display-4 text-uppercase text-white">Menu & Pricing</h1>
                <a href="">Home</a>
                <i class="far fa-square text-primary px-2"></i>
                <a href="">Menu & Pricing</a>
            </div>
        </div>
    </div>
    <!-- Page Header End -->


    <!-- Products Start -->
    <div class="container-fluid about py-5">
        <div class="container">
            <div class="section-title position-relative text-center mx-auto mb-5 pb-3" style="max-width: 600px;">
                <h2 class="text-primary font-secondary">Menu & Pricing</h2>
                <h1 class="display-4 text-uppercase">Explore Our Cakes</h1>
            </div>
            <?php

            // Lấy tất cả category
            $categories = [];
            $result = $conn->query("SELECT * FROM categories ORDER BY category_id ASC");
            while ($row = $result->fetch_assoc()) {
                $categories[$row['category_id']] = $row['category_name'];
            }

            // Lấy tất cả sản phẩm
            $products_by_category = [];
            $result = $conn->query("SELECT * FROM products ORDER BY product_id ASC");
            while ($row = $result->fetch_assoc()) {
                $cat_id = $row['category_id'];
                $products_by_category[$cat_id][] = $row;
            }
            ?>

            <div class="tab-class text-center">
                <ul class="nav nav-pills d-inline-flex justify-content-center bg-dark text-uppercase border-inner p-4 mb-5">
                    <?php
                    $first = true;
                    foreach ($categories as $cat_id => $cat_name): ?>
                        <li class="nav-item">
                            <a class="nav-link text-white <?= $first ? 'active' : '' ?>" data-bs-toggle="pill" href="#tab-<?= $cat_id ?>">
                                <?= htmlspecialchars($cat_name) ?>
                            </a>
                        </li>
                    <?php $first = false;
                    endforeach; ?>
                </ul>

                <div class="tab-content">
                    <?php
                    $first = true;
                    foreach ($categories as $cat_id => $cat_name): ?>
                        <div id="tab-<?= $cat_id ?>" class="tab-pane fade p-0 <?= $first ? 'show active' : '' ?>">
                            <div class="row g-3">
                                <?php
                                if (!empty($products_by_category[$cat_id])):
                                    foreach ($products_by_category[$cat_id] as $product):
                                ?>
                                        <div class="col-lg-6">
                                            <div class="d-flex h-100">
                                                <div class="flex-shrink-0 text-center">
                                                    <img class="img-fluid" src="../../assets/img/<?= htmlspecialchars($product['image']) ?>" alt="" style="width: 150px; height: 85px;">
                                                    <h4 class="bg-dark text-primary p-2 m-0"><?= number_format($product['price'], 0, ',', '.') ?>₫</h4>
                                                    <form method="post" action="add-to-cart.php">
                                                        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                                        <button type="submit" class="btn btn-primary cart-btn mt-2">
                                                            <i class="fa fa-shopping-cart"></i> Add to Cart
                                                        </button>
                                                    </form>
                                                </div>
                                                <div class="d-flex flex-column justify-content-center text-start bg-secondary border-inner px-4 flex-grow-1">
                                                    <h5 class="text-uppercase"><?= htmlspecialchars($product['product_name']) ?></h5>
                                                    <span><?= htmlspecialchars($product['description']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    endforeach;
                                else: ?>
                                    <p class="text-center">Hiện chưa có sản phẩm trong danh mục này.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php $first = false;
                    endforeach; ?>
                </div>
            </div>

        </div>
    </div>
    <!-- Products End -->


    <!-- Offer Start -->
    <div class="container-fluid bg-offer my-5 py-5">
        <div class="container py-5">
            <div class="row gx-5 justify-content-center">
                <div class="col-lg-7 text-center">
                    <div class="section-title position-relative text-center mx-auto mb-4 pb-3" style="max-width: 600px;">
                        <h2 class="text-primary font-secondary">Special Kombo Pack</h2>
                        <h1 class="display-4 text-uppercase text-white">Super Crispy Cakes</h1>
                    </div>
                    <p class="text-white mb-4">Eirmod sed tempor lorem ut dolores sit kasd ipsum. Dolor ea et dolore et at sea ea at dolor justo ipsum duo rebum sea. Eos vero eos vero ea et dolore eirmod et. Dolores diam duo lorem. Elitr ut dolores magna sit. Sea dolore sed et.</p>
                    <a href="" class="btn btn-primary border-inner py-3 px-5 me-3">Shop Now</a>
                    <a href="" class="btn btn-dark border-inner py-3 px-5">Read More</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Offer End -->
    

    <!-- Footer Start -->
    <?php include 'footer.php'; ?>
    <!-- Footer End -->


    <!-- Back to Top -->
    <a href="#" class="btn btn-primary border-inner py-3 fs-4 back-to-top"><i class="bi bi-arrow-up"></i></a>


    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/lib/easing/easing.min.js"></script>
    <script src="../../assets/lib/waypoints/waypoints.min.js"></script>
    <script src="../../assets/lib/counterup/counterup.min.js"></script>
    <script src="../../assets/lib/owlcarousel/owl.carousel.min.js"></script>

    <!-- Template Javascript -->
    <script src="../../assets/js/main.js"></script>
</body>

</html>