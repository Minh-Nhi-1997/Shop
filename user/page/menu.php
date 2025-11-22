<?php
session_start();
require './connect-db.php';

// Nếu chưa đăng nhập, redirect tới login
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.html");
    exit;
}

// Lấy danh mục
$categories = [];
$resCat = $conn->query("SELECT * FROM categories ORDER BY category_id ASC");
while ($row = $resCat->fetch_assoc()) {
    $categories[$row['category_id']] = $row['category_name'];
}

// Lấy category từ GET (nếu có)
$filterCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Phân trang
$limit = 6; // số sản phẩm/trang
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Tổng sản phẩm theo filter
if ($filterCategory) {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id=?");
    $stmtTotal->bind_param("i", $filterCategory);
} else {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM products");
}
$stmtTotal->execute();
$total_products = $stmtTotal->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);
$stmtTotal->close();

// Lấy sản phẩm theo filter + phân trang
if ($filterCategory) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id=? ORDER BY product_id ASC LIMIT ?, ?");
    $stmt->bind_param("iii", $filterCategory, $start, $limit);
} else {
    $stmt = $conn->prepare("SELECT * FROM products ORDER BY product_id ASC LIMIT ?, ?");
    $stmt->bind_param("ii", $start, $limit);
}
$stmt->execute();
$products_result = $stmt->get_result();
$products = [];
while ($row = $products_result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Menu & Pricing - CakeZone</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid bg-dark bg-img p-5 mb-5 text-center">
        <h1 class="display-4 text-uppercase text-white">Menu & Pricing</h1>
        <a href="index.php" class="text-white">Home</a>
        <i class="far fa-square text-primary px-2"></i>
        <span class="text-white">Menu & Pricing</span>
    </div>

    <div class="container py-5">
        <div class="section-title text-center mx-auto mb-5 pb-3" style="max-width:600px;">
            <h2 class="text-primary font-secondary">Menu & Pricing</h2>
            <h1 class="display-4 text-uppercase">Explore Our Cakes</h1>
        </div>

        <div class="tab-class text-center">
            <ul class="nav nav-pills d-inline-flex justify-content-center bg-dark text-uppercase border-inner p-4 mb-5">
                <?php
                $first = true;
                foreach ($categories as $cat_id => $cat_name): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white <?= ($filterCategory == $cat_id || ($filterCategory == 0 && $first)) ? 'active' : '' ?>"
                            data-category="<?= $cat_id ?>" href="#">
                            <?= htmlspecialchars($cat_name) ?>
                        </a>
                    </li>
                <?php $first = false;
                endforeach; ?>
            </ul>

            <div class="row g-4">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="card h-100 border-inner">
                                <img class="card-img-top" src="../../assets/img/<?= htmlspecialchars($product['image']) ?>"
                                    alt="<?= htmlspecialchars($product['product_name']) ?>"
                                    style="height:200px; object-fit:cover;">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title text-uppercase"><?= htmlspecialchars($product['product_name']) ?></h5>
                                    <p class="card-text flex-grow-1"><?= htmlspecialchars($product['description']) ?></p>
                                    <h6 class="bg-dark text-primary p-2 text-center"><?= number_format($product['price'], 0, ',', '.') ?>₫</h6>
                                    <form method="post" action="add-to-cart.php" class="mt-2">
                                        <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fa fa-shopping-cart"></i> Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center">Hiện chưa có sản phẩm trong danh mục này.</p>
                <?php endif; ?>
            </div>


            <!-- Phân trang -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page - 1 ?>&category=<?= $filterCategory ?>">« Trước</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&category=<?= $filterCategory ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?page=<?= $page + 1 ?>&category=<?= $filterCategory ?>">Tiếp »</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.nav-link[data-category]').on('click', function(e) {
                e.preventDefault();
                const categoryId = $(this).data('category');
                // Chuyển sang trang 1 của category được chọn
                window.location.href = '?page=1&category=' + categoryId;
            });
        });
    </script>
</body>

</html>