<?php
session_start();
require './connect-db.php';

// Auth simple
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}
// Kiểm tra phân quyền admin
if (($_SESSION['role'] ?? '') !== 'admin') {
    // Nếu là customer hoặc role khác admin
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Bạn không có quyền truy cập trang này!</h2>";
    echo "<p style='text-align:center;'><a href='index.php'>Quay lại trang chủ</a></p>";
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);

    if ($action === 'add') {
        $imageName = 'default.jpg';
        if (!empty($_FILES['image']['name'])) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageName = uniqid('prod_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../assests/img/' . $imageName);
        }

        $stmt = $conn->prepare("INSERT INTO products (product_name, category_id, price, stock, description, image, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $stmt->bind_param("sidiss", $name, $category_id, $price, $stock, $desc, $imageName);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: admin-products.php');
        exit;
    }

    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $imageName = $_POST['old_image'] ?? 'default.jpg';

        if (!empty($_FILES['image']['name'])) {
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $newImageName = uniqid('prod_') . '.' . $ext;
            $uploadPath = '../assests/img/' . $newImageName;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                // Xóa ảnh cũ nếu có và không phải default.jpg
                if ($imageName && $imageName !== 'default.jpg') {
                    $oldFilePath = '../assests/img/' . $imageName;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
                $imageName = $newImageName; // cập nhật tên ảnh mới
            }
        }

        $stmt = $conn->prepare("UPDATE products SET product_name=?, category_id=?, price=?, stock=?, description=?, image=? WHERE product_id=?");
        if ($stmt) {
            $stmt->bind_param("sidissi", $name, $category_id, $price, $stock, $desc, $imageName, $id);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: admin-products.php');
        exit;
    }
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);

    // 1. Lấy tên ảnh trước khi xóa
    $stmtImg = $conn->prepare("SELECT image FROM products WHERE product_id = ?");
    if ($stmtImg) {
        $stmtImg->bind_param("i", $delId);
        $stmtImg->execute();
        $stmtImg->bind_result($imageName);
        $stmtImg->fetch();
        $stmtImg->close();

        // 2. Xóa file ảnh nếu tồn tại và không phải default.jpg
        if ($imageName && $imageName !== 'default.jpg') {
            $filePath = '../assests/img/' . $imageName;
            if (file_exists($filePath)) {
                unlink($filePath); // xóa file vật lý
            }
        }
    }

    // 3. Xóa record trong DB
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $delId);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: admin-products.php');
    exit;
}


// Fetch products with category name
$products = [];
$sql = "SELECT p.product_id, p.product_name, p.price, p.stock, p.description, p.image, c.category_name, p.category_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        ORDER BY p.created_at DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $products[] = $row;
    }
    $res->free();
}

// Fetch categories for modal select
$categories = [];
$resCat = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categories[] = $row;
    }
    $resCat->free();
}

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
    <link href="../assests/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assests/css/style.css" rel="stylesheet">
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
                <div class="mb-3 d-flex gap-2">
                    <select id="filterCategory" class="form-control" style="max-width:250px;">
                        <option value="">-- Tất cả danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id']; ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input id="searchInput" type="text" class="form-control" placeholder="Tìm kiếm sản phẩm...">
                </div>


                <div id="productsList">
                    <?php if (empty($products)): ?>
                        <p class="text-muted">Chưa có sản phẩm nào.</p>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <div class="product-card">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <img src="../assests/img/<?= htmlspecialchars($p['image'] ?? 'default.jpg'); ?>" alt="<?= htmlspecialchars($p['product_name']); ?>" style="width:100%; height:80px; object-fit:cover; border-radius:5px;">
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
                                            <a href="admin-products.php?delete_id=<?= $p['product_id']; ?>" class="btn btn-sm btn-danger delete-link" data-name="<?= htmlspecialchars($p['product_name'], ENT_QUOTES); ?>">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
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
        // Search filter
        function filterProducts() {
            const q = $('#searchInput').val().toLowerCase();
            const cat = $('#filterCategory').val();

            $('#productsList .product-card').each(function() {
                const text = $(this).text().toLowerCase();
                const productCat = $(this).find('.badge').data('category-id'); // lưu category_id trong badge
                const matchSearch = text.indexOf(q) !== -1;
                const matchCategory = !cat || productCat == cat;
                $(this).toggle(matchSearch && matchCategory);
            });
        }

        // Search filter
        $('#searchInput').on('keyup', filterProducts);

        // Category filter
        $('#filterCategory').on('change', filterProducts);


        // Open add modal
        $('#btnAdd').on('click', function() {
            $('#modalTitle').text('Thêm sản phẩm');
            $('#formAction').val('add');
            $('#productForm')[0].reset();
            $('#productId').val('');
            $('#oldImage').val('');
            $('#previewImage').hide();
        });

        // Open edit modal
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
                $('#previewImage').attr('src', '../assests/img/' + btn.data('image')).show();
            } else {
                $('#previewImage').hide();
            }
            new bootstrap.Modal(document.getElementById('productModal')).show();
        });

        // Image preview
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

        // Delete confirm
        $('.delete-link').on('click', function(e) {
            if (!confirm('Bạn chắc chắn muốn xóa "' + $(this).data('name') + '" ?')) e.preventDefault();
        });
    </script>
    <script src="../assests/js/active.js"></script>
</body>

</html>