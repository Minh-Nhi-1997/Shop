<?php
session_start();
require './connect-db.php'; // Kết nối MySQLi

// Kiểm tra đăng nhập
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.html');
    exit;
}

// Xử lý POST (thêm / sửa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($action === 'add') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO customers (full_name, email, password_hash, phone, address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $full_name, $email, $hashedPassword, $phone, $address);
        $stmt->execute();
        $stmt->close();
        header('Location: admin-customers.php?success=add');
        exit;
    }

    if ($action === 'edit') {
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE customers SET full_name=?, email=?, password_hash=?, phone=?, address=? WHERE customer_id=?");
            $stmt->bind_param("sssssi", $full_name, $email, $hashedPassword, $phone, $address, $id);
        } else {
            $stmt = $conn->prepare("UPDATE customers SET full_name=?, email=?, phone=?, address=? WHERE customer_id=?");
            $stmt->bind_param("ssssi", $full_name, $email, $phone, $address, $id);
        }
        $stmt->execute();
        $stmt->close();
        header('Location: admin-customers.php?success=edit');
        exit;
    }
}

// Xử lý xóa
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
    $stmt->bind_param("i", $delId);
    $stmt->execute();
    $stmt->close();
    header('Location: admin-customers.php?success=delete');
    exit;
}

// Lấy danh sách khách hàng
$customers = [];
$res = $conn->query("SELECT customer_id, full_name, email, phone, address, created_at FROM customers ORDER BY created_at DESC");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $customers[] = $row;
    }
    $res->free();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Quản lý khách hàng - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
<link href="../assests/css/bootstrap.min.css" rel="stylesheet">
<style>
.sidebar { background:#2c3e50; min-height:100vh; padding:20px; }
.sidebar a { color:#fff; display:block; padding:10px 15px; border-radius:5px; margin-bottom:5px; text-decoration:none;}
.sidebar a:hover, .sidebar a.active { background:#fd7e14; }
.main-content { padding:30px; }
.customer-card { border:1px solid #ddd; border-radius:8px; padding:15px; margin-bottom:15px; background:#fff; }
.btn-group-action { display:flex; gap:5px; justify-content:flex-end; }
.modal-header { background:#fd7e14; color:#fff; }
#alertContainer { position:fixed; top:20px; right:20px; z-index:1050; }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>

        <div class="col-md-9 main-content">
            <div id="alertContainer"></div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Quản lý khách hàng</h2>
                <button class="btn btn-primary border-inner" id="btnAdd"><i class="fa fa-plus"></i> Thêm khách hàng</button>
            </div>

            <div class="mb-3">
                <input id="searchInput" type="text" class="form-control" placeholder="Tìm kiếm khách hàng...">
            </div>

            <div id="customersList">
                <?php if (empty($customers)): ?>
                    <p class="text-muted">Chưa có khách hàng nào.</p>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <div class="customer-card">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6><?= htmlspecialchars($c['full_name']); ?></h6>
                                    <p class="small text-muted mb-1"><?= htmlspecialchars($c['email']); ?></p>
                                    <p class="small text-muted mb-1">SĐT: <?= htmlspecialchars($c['phone']); ?></p>
                                    <p class="small text-muted mb-1">Địa chỉ: <?= htmlspecialchars($c['address']); ?></p>
                                    <p class="small text-muted">Ngày tạo: <?= date('d/m/Y H:i', strtotime($c['created_at'])); ?></p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="btn-group-action">
                                        <button class="btn btn-sm btn-warning edit-btn"
                                            data-id="<?= $c['customer_id']; ?>"
                                            data-full_name="<?= htmlspecialchars($c['full_name'], ENT_QUOTES); ?>"
                                            data-email="<?= htmlspecialchars($c['email'], ENT_QUOTES); ?>"
                                            data-phone="<?= htmlspecialchars($c['phone'], ENT_QUOTES); ?>"
                                            data-address="<?= htmlspecialchars($c['address'], ENT_QUOTES); ?>">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <a href="admin-customers.php?delete_id=<?= $c['customer_id']; ?>" class="btn btn-sm btn-danger delete-link" data-name="<?= htmlspecialchars($c['full_name'], ENT_QUOTES); ?>">
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
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="post" id="customerForm">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Thêm khách hàng</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="customerId" value="">

                <div class="mb-3">
                    <label class="form-label">Họ và tên</label>
                    <input name="full_name" id="customerName" type="text" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input name="email" id="customerEmail" type="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input name="password" id="customerPassword" type="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">SĐT</label>
                    <input name="phone" id="customerPhone" type="text" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Địa chỉ</label>
                    <textarea name="address" id="customerAddress" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-primary border-inner" id="saveBtnModal">Lưu khách hàng</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search filter
$('#searchInput').on('keyup', function() {
    const q = $(this).val().toLowerCase();
    $('#customersList .customer-card').each(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
    });
});

// Thông báo
function showAlert(message, type='success') {
    const alertId = 'alert-' + Date.now();
    const html = `<div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    $('#alertContainer').append(html);
    setTimeout(() => { $('#' + alertId).alert('close'); }, 4000);
}

// Hiển thị thông báo dựa trên GET param
const urlParams = new URLSearchParams(window.location.search);
const success = urlParams.get('success');
if (success === 'add') showAlert('Thêm khách hàng thành công!');
if (success === 'edit') showAlert('Cập nhật khách hàng thành công!');
if (success === 'delete') showAlert('Xóa khách hàng thành công!');

// Open add modal
$('#btnAdd').on('click', function() {
    $('#modalTitle').text('Thêm khách hàng');
    $('#formAction').val('add');
    $('#customerForm')[0].reset();
    $('#customerId').val('');
    $('#customerPassword').prop('required', true);
    new bootstrap.Modal(document.getElementById('customerModal')).show();
});

// Open edit modal
$('.edit-btn').on('click', function() {
    const btn = $(this);
    $('#modalTitle').text('Sửa khách hàng');
    $('#formAction').val('edit');
    $('#customerId').val(btn.data('id'));
    $('#customerName').val(btn.data('full_name'));
    $('#customerEmail').val(btn.data('email'));
    $('#customerPhone').val(btn.data('phone'));
    $('#customerAddress').val(btn.data('address'));
    $('#customerPassword').prop('required', false);
    new bootstrap.Modal(document.getElementById('customerModal')).show();
});

// Delete confirm
$('.delete-link').on('click', function(e){
    if(!confirm('Bạn chắc chắn muốn xóa "'+$(this).data('name')+'" ?')) e.preventDefault();
});
</script>
<script src="../assests/js/active.js"></script>

</body>
</html>
