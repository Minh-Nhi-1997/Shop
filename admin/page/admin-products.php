<?php
session_start();
require '../../user/page/connect-db.php';

// Kiểm tra admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Folder upload
$targetDir = __DIR__ . '/../../assets/img/';
if (!is_dir($targetDir)) die("Folder upload không tồn tại: $targetDir");
if (!is_writable($targetDir)) die("Folder upload không có quyền ghi");

// --- Xử lý POST (Thêm/Sửa) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $imageName = $_POST['old_image'] ?? 'default.jpg';

    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) die("Chỉ được upload file ảnh: jpg, jpeg, png, gif");

        $newImageName = uniqid('prod_') . '.' . $ext;
        $uploadPath = $targetDir . $newImageName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            if ($imageName !== 'default.jpg') {
                $oldPath = $targetDir . $imageName;
                if (file_exists($oldPath)) unlink($oldPath);
            }
            $imageName = $newImageName;
        }
    }

    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO products (product_name, category_id, price, stock, description, image, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sidiss", $name, $category_id, $price, $stock, $desc, $imageName);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE products SET product_name=?, category_id=?, price=?, stock=?, description=?, image=? WHERE product_id=?");
        $stmt->bind_param("sidissi", $name, $category_id, $price, $stock, $desc, $imageName, $id);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect giữ filter
    $page = $_POST['page'] ?? 1;
    $category = $_POST['category'] ?? '';
    $q = $_POST['q'] ?? '';
    $url = "admin-products.php?page=$page";
    if ($category) $url .= "&category=$category";
    if ($q) $url .= "&q=" . urlencode($q);
    header("Location: $url");
    exit;
}

// --- Xử lý xóa ---
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);

    $stmt = $conn->prepare("UPDATE products SET is_active=0 WHERE product_id=?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $stmt->close();

    // Giữ filter khi xóa
    $page = $_GET['page'] ?? 1;
    $category = $_GET['category'] ?? '';
    $q = $_GET['q'] ?? '';
    $url = "admin-products.php?page=$page";
    if ($category) $url .= "&category=$category";
    if ($q) $url .= "&q=" . urlencode($q);
    header("Location: $url");
    exit;
}


// --- Lấy danh mục ---
$categories = [];
$resCat = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) $categories[] = $row;
    $resCat->free();
}

// --- PHÂN TRANG + Filter ---
$perPage = 3;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

$filterCategory = isset($_GET['category']) ? intval($_GET['category']) : '';
$searchQ = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = "1";
$params = [];
$types = '';

$where = "p.is_active=1"; // thay cho "1"
$params = [];
$types = '';
if ($filterCategory) {
    $where .= " AND p.category_id=?";
    $params[] = $filterCategory;
    $types .= 'i';
}
if ($searchQ) {
    $where .= " AND p.product_name LIKE ?";
    $params[] = "%$searchQ%";
    $types .= 's';
}

// Tổng số sản phẩm
$stmtTotal = $conn->prepare("SELECT COUNT(*) FROM products p WHERE $where");
if ($types) $stmtTotal->bind_param($types, ...$params);
$stmtTotal->execute();
$stmtTotal->bind_result($totalProducts);
$stmtTotal->fetch();
$stmtTotal->close();
$totalPages = ceil($totalProducts / $perPage);

// Lấy sản phẩm theo trang
$sql = "SELECT p.product_id, p.product_name, p.price, p.stock, p.description, p.image, c.category_name, p.category_id
        FROM products p
        LEFT JOIN categories c ON p.category_id=c.category_id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$products = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Quản lý sản phẩm - CakeZone Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
            padding: 20px;
        }

        .sidebar a {
            color: #fff;
            display: block;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
            text-decoration: none;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #fd7e14;
        }

        .main-content {
            padding: 30px;
        }

        .product-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }

        .btn-group-action {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }

        .modal-header {
            background: #fd7e14;
            color: #fff;
        }

        .pagination {
            justify-content: center;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            <div class="col-md-9 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Quản lý sản phẩm</h2>
                    <button class="btn btn-primary border-inner" data-bs-toggle="modal" data-bs-target="#productModal" id="btnAdd"><i class="fa fa-plus"></i> Thêm sản phẩm</button>
                </div>

                <form method="get" class="mb-3 d-flex gap-2">
                    <select name="category" id="filterCategory" class="form-control" style="max-width:250px;" onchange="this.form.submit()">
                        <option value="">-- Tất cả danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id']; ?>" <?= ($filterCategory == $cat['category_id']) ? 'selected' : ''; ?>><?= htmlspecialchars($cat['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="q" value="<?= htmlspecialchars($searchQ); ?>" class="form-control" placeholder="Tìm kiếm sản phẩm...">
                    <button type="submit" class="btn btn-primary">Tìm</button>
                </form>

                <div id="productsList">
                    <?php if (empty($products)): ?>
                        <p class="text-muted">Chưa có sản phẩm nào.</p>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <div class="product-card">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <img src="<?= '../../assets/img/' . htmlspecialchars($p['image'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($p['product_name']); ?>" style="width:100%; height:80px; object-fit:cover; border-radius:5px;">
                                    </div>
                                    <div class="col-md-6">
                                        <h6><?= htmlspecialchars($p['product_name']); ?></h6>
                                        <p class="small text-muted mb-1"><?= htmlspecialchars($p['description']); ?></p>
                                        <div>
                                            <span class="badge bg-primary" data-category-id="<?= $p['category_id']; ?>">
                                                <?= htmlspecialchars($p['category_name'] ?? 'Chưa phân loại'); ?>
                                            </span>
                                            <span class="text-danger fw-bold ms-2"><?= number_format($p['price']); ?> VNĐ</span>
                                        </div>
                                        <p class="small mt-1">Tồn kho: <strong><?= (int)$p['stock']; ?></strong></p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="btn-group-action">
                                            <button class="btn btn-sm btn-warning edit-btn"
                                                data-id="<?= $p['product_id']; ?>"
                                                data-name="<?= htmlspecialchars($p['product_name'], ENT_QUOTES); ?>"
                                                data-price="<?= $p['price']; ?>"
                                                data-desc="<?= htmlspecialchars($p['description'], ENT_QUOTES); ?>"
                                                data-category-id="<?= $p['category_id']; ?>"
                                                data-stock="<?= $p['stock']; ?>"
                                                data-image="<?= $p['image']; ?>">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <a href="admin-products.php?delete_id=<?= $p['product_id']; ?>&page=<?= $page; ?>&category=<?= $filterCategory; ?>&q=<?= urlencode($searchQ); ?>" class="btn btn-sm btn-danger delete-link" data-name="<?= htmlspecialchars($p['product_name'], ENT_QUOTES); ?>">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Phân trang -->
                <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-center">

                            <!-- Nút "Trước" -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1; ?>&category=<?= $filterCategory; ?>&q=<?= urlencode($searchQ); ?>">« Trước</a>
                                </li>
                            <?php endif; ?>

                            <!-- Số trang -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?= $i; ?>&category=<?= $filterCategory; ?>&q=<?= urlencode($searchQ); ?>"><?= $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <!-- Nút "Tiếp" -->
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1; ?>&category=<?= $filterCategory; ?>&q=<?= urlencode($searchQ); ?>">Tiếp »</a>
                                </li>
                            <?php endif; ?>

                        </ul>
                    </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Modal thêm/sửa sản phẩm như cũ -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" enctype="multipart/form-data" id="productForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm sản phẩm</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="productId" value="">
                    <input type="hidden" name="old_image" id="oldImage" value="">
                    <input type="hidden" name="page" value="<?= $page; ?>">
                    <input type="hidden" name="category" value="<?= $filterCategory; ?>">
                    <input type="hidden" name="q" value="<?= htmlspecialchars($searchQ); ?>">
                    <!-- Các input còn lại như cũ -->
                    <div class="mb-3">
                        <label class="form-label">Tên sản phẩm</label>
                        <input name="name" id="productName" type="text" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Giá (VNĐ)</label>
                        <input name="price" id="productPrice" type="number" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" id="productDesc" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Danh mục</label>
                        <select name="category_id" id="productCategory" class="form-control" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Số lượng tồn kho</label>
                        <input name="stock" id="productStock" type="number" class="form-control" min="0" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ảnh sản phẩm</label>
                        <input type="file" name="image" id="productImage" class="form-control" accept="image/*">
                        <img id="previewImage" src="" alt="" style="width:100px; margin-top:5px; display:none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary border-inner" id="saveBtnModal">Lưu sản phẩm</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal thêm
        $('#btnAdd').on('click', function() {
            $('#modalTitle').text('Thêm sản phẩm');
            $('#formAction').val('add');
            $('#productForm')[0].reset();
            $('#productId').val('');
            $('#oldImage').val('');
            $('#previewImage').hide();
        });

        // Modal sửa
        $('.edit-btn').on('click', function() {
            const btn = $(this);
            $('#modalTitle').text('Sửa sản phẩm');
            $('#formAction').val('edit');
            $('#productId').val(btn.data('id'));
            $('#productName').val(btn.data('name'));
            $('#productPrice').val(btn.data('price'));
            $('#productDesc').val(btn.data('desc'));
            $('#productCategory').val(btn.data('category-id'));
            $('#productStock').val(btn.data('stock'));
            $('#oldImage').val(btn.data('image'));
            if (btn.data('image')) {
                $('#previewImage').attr('src', '../../assets/img/' + btn.data('image')).show();
            } else {
                $('#previewImage').hide();
            }
            new bootstrap.Modal(document.getElementById('productModal')).show();
        });

        // Preview ảnh
        $('#productImage').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#previewImage').attr('src', e.target.result).show();
                }
                reader.readAsDataURL(file);
            } else {
                $('#previewImage').hide();
            }
        });

        // Xác nhận xóa
        $('.delete-link').on('click', function(e) {
            if (!confirm('Bạn chắc chắn muốn xóa "' + $(this).data('name') + '" ?')) e.preventDefault();
        });
    </script>
    <script src="../../assets/js/active.js"></script>
</body>

</html>