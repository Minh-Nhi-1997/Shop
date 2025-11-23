<?php
session_start();
require './connect-db.php';

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

// Lấy category và page mặc định
$filterCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;
$limit = 6;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// Tổng sản phẩm (chỉ sản phẩm is_active=1)
if ($filterCategory) {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id=? AND is_active=1");
    $stmtTotal->bind_param("i", $filterCategory);
} else {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE is_active=1");
}
$stmtTotal->execute();
$total_products = $stmtTotal->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);
$stmtTotal->close();

// Lấy sản phẩm cho trang hiện tại (chỉ is_active=1)
$start = ($page - 1) * $limit;
if ($filterCategory) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id=? AND is_active=1 ORDER BY product_id ASC LIMIT ?, ?");
    $stmt->bind_param("iii", $filterCategory, $start, $limit);
} else {
    $stmt = $conn->prepare("SELECT * FROM products WHERE is_active=1 ORDER BY product_id ASC LIMIT ?, ?");
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

<div class="position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div id="cartToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">Đã thêm sản phẩm vào giỏ hàng!</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

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

    <!-- Nav danh mục -->
    <div class="d-flex justify-content-center mb-5">
        <ul class="nav nav-pills d-inline-flex justify-content-center bg-dark text-uppercase border-inner p-4 mb-5" id="category-nav">
            <li class="nav-item">
                <a class="nav-link text-white <?= $filterCategory == 0 ? 'active' : '' ?>" href="#" data-category="0">Tất cả</a>
            </li>
            <?php foreach ($categories as $cat_id => $cat_name): ?>
                <li class="nav-item">
                    <a class="nav-link text-white <?= $filterCategory == $cat_id ? 'active' : '' ?>" href="#" data-category="<?= $cat_id ?>"><?= htmlspecialchars($cat_name) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Sản phẩm -->
    <div id="product-list" class="row g-4">
        <?php if (!empty($products)): ?>
            <?php foreach ($products as $product): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100 border-inner">
                        <img class="card-img-top" src="../../assets/img/<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" style="height:200px; object-fit:cover;">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-uppercase"><?= htmlspecialchars($product['product_name']) ?></h5>
                            <p class="card-text flex-grow-1"><?= htmlspecialchars($product['description']) ?></p>
                            <h6 class="bg-dark text-primary p-2 text-center"><?= number_format($product['price'], 0, ',', '.') ?>₫</h6>
                            <button class="btn btn-primary w-100 add-to-cart-btn mt-2" data-id="<?= $product['product_id'] ?>"><i class="fa fa-shopping-cart"></i> Add to Cart</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center text-success">Hiện chưa có sản phẩm trong danh mục này.</p>
        <?php endif; ?>
    </div>

    <!-- Phân trang -->
    <div id="pagination" class="d-flex justify-content-center mt-4">
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="<?= $page - 1 ?>">« Trước</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>"><a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="#" data-page="<?= $page + 1 ?>">Tiếp »</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    let currentCategory = <?= $filterCategory ?>;

    function loadProducts(category, page) {
        $.get('fetch-products.php', {category: category, page: page}, function(data) {
            $('#product-list').html(data.productsHtml);
            $('#pagination').html(data.paginationHtml);
            currentCategory = category;
            $('#category-nav .nav-link').removeClass('active');
            $('#category-nav .nav-link[data-category="'+category+'"]').addClass('active');
        }, 'json');
    }

    $(document).on('click', '#category-nav .nav-link', function(e) {
        e.preventDefault();
        const category = $(this).data('category');
        loadProducts(category, 1);
    });

    $(document).on('click', '#pagination .page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadProducts(currentCategory, page);
    });

    $(document).on('click', '.add-to-cart-btn', function(e) {
        e.preventDefault();
        const product_id = $(this).data('id');
        $.post('add-to-cart.php', {product_id: product_id}, function(data) {
            if (data.success) {
                $('#cart-count').text(data.cart_count);
                const toastEl = document.getElementById('cartToast');
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            } else {
                alert(data.message || 'Bạn cần đăng nhập để thêm vào giỏ hàng!');
            }
        }, 'json');
    });
});
</script>
</body>
</html>
