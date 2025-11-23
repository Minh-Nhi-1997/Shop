<?php
session_start();
require './connect-db.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['customer_id'];
$global_msg = '';

// --- Xử lý POST: Hủy đơn / phản hồi ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hủy đơn
    if (isset($_POST['cancel_order_id'])) {
        $cancel_id = (int)$_POST['cancel_order_id'];
        $stmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id=? AND customer_id=?");
        $stmt->bind_param("ii", $cancel_id, $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $order = $res->fetch_assoc();
        $stmt->close();

        if ($order && !in_array($order['order_status'], ['completed','cancelled','shipped'])) { 
            // Không thể hủy completed, cancelled, shipped
            $stmt = $conn->prepare("UPDATE orders SET order_status='cancelled' WHERE order_id=?");
            $stmt->bind_param("i", $cancel_id);
            $stmt->execute();
            $stmt->close();
            $global_msg = "Đơn hàng #$cancel_id đã được hủy thành công.";
        } else {
            $global_msg = "Đơn hàng không thể hủy.";
        }
    }

    // Gửi phản hồi
    if (isset($_POST['submit_feedback'])) {
        $order_id = (int)$_POST['order_id'];
        $message = trim($_POST['message']);
        if ($message === '') $global_msg = "Vui lòng nhập nội dung phản hồi.";
        else {
            $stmt = $conn->prepare("SELECT created_at FROM orders WHERE order_id=? AND customer_id=? AND order_status='completed'");
            $stmt->bind_param("ii", $order_id, $uid);
            $stmt->execute();
            $res = $stmt->get_result();
            $order = $res->fetch_assoc();
            $stmt->close();

            if (!$order) $global_msg = "Đơn hàng không tồn tại hoặc chưa hoàn thành.";
            else {
                $diff_days = (time() - strtotime($order['created_at'])) / (60*60*24);
                if ($diff_days > 1) $global_msg = "Quá 1 ngày kể từ khi hoàn thành đơn hàng. Không thể gửi phản hồi."; // sửa thành 1 ngày
                else {
                    $stmt = $conn->prepare("SELECT feedback_id FROM feedbacks WHERE order_id=? AND customer_id=?");
                    $stmt->bind_param("ii", $order_id, $uid);
                    $stmt->execute();
                    $res_fb = $stmt->get_result();
                    $existing_feedback = $res_fb->fetch_assoc();
                    $stmt->close();

                    if ($existing_feedback) $global_msg = "Đơn hàng này bạn đã phản hồi trước đó.";
                    else {
                        $stmt = $conn->prepare("INSERT INTO feedbacks (customer_id, order_id, message, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->bind_param("iis", $uid, $order_id, $message);
                        $stmt->execute();
                        $stmt->close();
                        $global_msg = "Gửi phản hồi thành công!";
                    }
                }
            }
        }
    }
}


// --- Lọc trạng thái ---
$filter_status = $_GET['status'] ?? 'all';
$valid_statuses = ['pending','processing','shipped','completed','cancelled'];
if (!in_array($filter_status, $valid_statuses)) $filter_status = 'all';

// --- Phân trang ---
$perPage = 5;
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset = ($page-1)*$perPage;

// Tổng số đơn hàng theo trạng thái
if ($filter_status === 'all') {
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=?");
    $stmt_count->bind_param("i",$uid);
} else {
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM orders WHERE customer_id=? AND order_status=?");
    $stmt_count->bind_param("is",$uid,$filter_status);
}
$stmt_count->execute();
$stmt_count->bind_result($totalOrders);
$stmt_count->fetch();
$stmt_count->close();
$totalPages = max(1, ceil($totalOrders / $perPage));

// Lấy danh sách đơn hàng theo trang và trạng thái
if ($filter_status === 'all') {
    $stmt = $conn->prepare("SELECT order_id, total_amount, order_status, created_at FROM orders WHERE customer_id=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("iii",$uid,$perPage,$offset);
} else {
    $stmt = $conn->prepare("SELECT order_id, total_amount, order_status, created_at FROM orders WHERE customer_id=? AND order_status=? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("isii",$uid,$filter_status,$perPage,$offset);
}
$stmt->execute();
$res = $stmt->get_result();
$orders = [];
while($row = $res->fetch_assoc()) $orders[] = $row;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Lịch sử đơn hàng - CakeZone</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="../../assets/css/bootstrap.min.css" rel="stylesheet">
<link href="../../assets/css/style.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
<style>
.status-badge.pending { background:#ffc107; color:#000; }
.status-badge.processing { background:#17a2b8; color:#fff; }
.status-badge.shipped { background:#0d6efd; color:#fff; }
.status-badge.completed { background:#198754; color:#fff; }
.status-badge.cancelled { background:#dc3545; color:#fff; }
.feedback-box { background:#343a40; padding:10px; margin-top:10px; border-radius:5px; }
.order-img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
}

</style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="container py-5">
    <h2 class="mb-4">Đơn hàng của bạn</h2>

    <?php if($global_msg): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?= htmlspecialchars($global_msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter trạng thái -->
    <form method="get" class="mb-3">
        <label>Chọn trạng thái: </label>
        <select name="status" class="form-select w-auto d-inline-block" onchange="this.form.submit()">
            <option value="all" <?= $filter_status==='all'?'selected':'' ?>>Tất cả</option>
            <option value="pending" <?= $filter_status==='pending'?'selected':'' ?>>Chờ xử lý</option>
            <option value="processing" <?= $filter_status==='processing'?'selected':'' ?>>Đang xử lý</option>
            <option value="shipped" <?= $filter_status==='shipped'?'selected':'' ?>>Đang giao</option>
            <option value="completed" <?= $filter_status==='completed'?'selected':'' ?>>Hoàn thành</option>
            <option value="cancelled" <?= $filter_status==='cancelled'?'selected':'' ?>>Đã hủy</option>
        </select>
    </form>

    <?php if(empty($orders)): ?>
        <p>Bạn chưa có đơn hàng nào.</p>
    <?php else: ?>
        <table class="table table-dark table-hover align-middle">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Tổng tiền</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($orders as $order):
                $oid = (int)$order['order_id'];
                $status = strtolower($order['order_status']);
                $order_completed = $status === 'completed';
                $days_diff = $order_completed ? (time() - strtotime($order['created_at']))/(60*60*24) : 0;
                $can_feedback = $order_completed && $days_diff <= 1;
                $can_cancel = !in_array($status,['shipped','completed','cancelled']);

                $feedback_text = '';
                if($order_completed) {
                    $stmt_fb = $conn->prepare("SELECT message FROM feedbacks WHERE order_id=? AND customer_id=?");
                    $stmt_fb->bind_param("ii",$oid,$uid);
                    $stmt_fb->execute();
                    $res_fb = $stmt_fb->get_result();
                    $fb = $res_fb->fetch_assoc();
                    if($fb) $feedback_text = $fb['message'];
                    $stmt_fb->close();
                }
            ?>
            <tr>
                <td>#<?= $oid ?></td>
                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                <td><span class="badge status-badge <?= $status ?>"><?= ucfirst($status) ?></span></td>
                <td class="text-end"><?= number_format($order['total_amount'],0,',','.') ?>₫</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-light" data-bs-toggle="collapse" data-bs-target="#items-<?= $oid ?>">Chi tiết</button>
                    <?php if($can_feedback): ?>
                        <button class="btn btn-sm btn-outline-info ms-1" data-bs-toggle="collapse" data-bs-target="#feedback-<?= $oid ?>">Phản hồi</button>
                    <?php endif; ?>
                    <?php if($can_cancel): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="cancel_order_id" value="<?= $oid ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Bạn chắc chắn muốn hủy đơn này?')">Hủy</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>

            <!-- Chi tiết sản phẩm -->
            <tr class="collapse-row">
                <td colspan="5" class="p-0">
                    <div class="collapse" id="items-<?= $oid ?>">
                        <div class="p-3 bg-secondary">
                            <table class="table table-sm table-dark mb-0">
                                <thead>
                                    <tr>
                                        <th>Hình ảnh</th>
                                        <th>Sản phẩm</th>
                                        <th class="text-center">Số lượng</th>
                                        <th class="text-end">Đơn giá</th>
                                        <th class="text-end">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $stmt2 = $conn->prepare("
                                    SELECT oi.quantity, oi.price, p.product_name, p.image
                                    FROM order_items oi
                                    JOIN products p ON oi.product_id = p.product_id
                                    WHERE oi.order_id=?
                                ");
                                $stmt2->bind_param("i",$oid);
                                $stmt2->execute();
                                $res2 = $stmt2->get_result();
                                $sum = 0;
                                while($it = $res2->fetch_assoc()):
                                    $line = $it['price']*$it['quantity'];
                                    $sum += $line;
                                ?>
                                    <tr>
                                        <td><img class="order-img" src="../../assets/img/<?= $it['image'] ?>" alt="<?= $it['image'] ?>"></td>
                                        <td><?= $it['product_name'] ?></td>
                                        <td class="text-center"><?= (int)$it['quantity'] ?></td>
                                        <td class="text-end"><?= number_format($it['price'],0,',','.') ?>₫</td>
                                        <td class="text-end"><?= number_format($line,0,',','.') ?>₫</td>
                                    </tr>
                                <?php endwhile; $stmt2->close(); ?>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Tổng</strong></td>
                                        <td class="text-end"><strong><?= number_format($sum,0,',','.') ?>₫</strong></td>
                                    </tr>
                                    <?php if($feedback_text): ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="feedback-box text-light"><?= nl2br(htmlspecialchars($feedback_text)) ?></div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>

            <!-- Form phản hồi -->
            <?php if($can_feedback): ?>
            <tr class="collapse-row">
                <td colspan="5" class="p-0">
                    <div class="collapse" id="feedback-<?= $oid ?>">
                        <div class="p-3 bg-secondary">
                            <form method="post">
                                <input type="hidden" name="order_id" value="<?= $oid ?>">
                                <textarea class="form-control mb-2" name="message" rows="3" placeholder="Nhập phản hồi..." required></textarea>
                                <button type="submit" name="submit_feedback" class="btn btn-sm btn-primary">Gửi phản hồi</button>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- PHÂN TRANG -->
        <?php if($totalPages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center">
                <?php if($page>1): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page-1 ?>&status=<?= $filter_status ?>">« Trước</a></li>
                <?php endif; ?>
                <?php for($i=1;$i<=$totalPages;$i++): ?>
                    <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&status=<?= $filter_status ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <?php if($page<$totalPages): ?>
                    <li class="page-item"><a class="page-link" href="?page=<?= $page+1 ?>&status=<?= $filter_status ?>">Tiếp »</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
<?php $conn->close(); ?>
<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
