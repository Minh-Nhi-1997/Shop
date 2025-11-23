<?php
session_start();
require './connect-db.php';

header('Content-Type: application/json');

$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 6;
$start = ($page - 1) * $limit;

// Tổng sản phẩm theo category (chỉ is_active=1)
if ($category) {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id=? AND is_active=1");
    $stmtTotal->bind_param("i", $category);
} else {
    $stmtTotal = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE is_active=1");
}
$stmtTotal->execute();
$total_products = $stmtTotal->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);
$stmtTotal->close();

// Lấy sản phẩm theo category + phân trang (chỉ is_active=1)
if ($category) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE category_id=? AND is_active=1 ORDER BY product_id ASC LIMIT ?, ?");
    $stmt->bind_param("iii", $category, $start, $limit);
} else {
    $stmt = $conn->prepare("SELECT * FROM products WHERE is_active=1 ORDER BY product_id ASC LIMIT ?, ?");
    $stmt->bind_param("ii", $start, $limit);
}
$stmt->execute();
$result = $stmt->get_result();

$productsHtml = '';
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $productsHtml .= '
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-inner">
                <img class="card-img-top" src="../../assets/img/' . htmlspecialchars($row['image']) . '" alt="' . htmlspecialchars($row['product_name']) . '" style="height:200px; object-fit:cover;">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title text-uppercase">' . htmlspecialchars($row['product_name']) . '</h5>
                    <p class="card-text flex-grow-1">' . htmlspecialchars($row['description']) . '</p>
                    <h6 class="bg-dark text-primary p-2 text-center">' . number_format($row['price'], 0, ',', '.') . '₫</h6>
                    <button class="btn btn-primary w-100 add-to-cart-btn mt-2" data-id="' . $row['product_id'] . '"><i class="fa fa-shopping-cart"></i> Add to Cart</button>
                </div>
            </div>
        </div>';
    }
} else {
    $productsHtml = '<p class="text-center text-success">Hiện chưa có sản phẩm trong danh mục này.</p>';
}
$stmt->close();

// Tạo phân trang
$paginationHtml = '';
if ($total_pages > 1) {
    $paginationHtml = '<nav><ul class="pagination">';
    if ($page > 1) {
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page - 1) . '">« Trước</a></li>';
    }
    for ($i = 1; $i <= $total_pages; $i++) {
        $active = ($i == $page) ? 'active' : '';
        $paginationHtml .= '<li class="page-item ' . $active . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
    }
    if ($page < $total_pages) {
        $paginationHtml .= '<li class="page-item"><a class="page-link" href="#" data-page="' . ($page + 1) . '">Tiếp »</a></li>';
    }
    $paginationHtml .= '</ul></nav>';
}

echo json_encode([
    'productsHtml' => $productsHtml,
    'paginationHtml' => $paginationHtml
]);
?>
